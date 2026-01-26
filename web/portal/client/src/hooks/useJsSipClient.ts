import { useCallback, useEffect, useRef, useState } from 'react';
import { UA, WebSocketInterface } from 'jssip';
import type { UAConfiguration, CallOptions, IncomingRTCSessionEvent, OutgoingRTCSessionEvent } from 'jssip/src/UA';
import type { RTCSession, AnswerOptions } from 'jssip/src/RTCSession';

// Monkey-patch JsSIP to accept all incoming calls regardless of R-URI user
// JsSIP by default rejects INVITEs where the R-URI user doesn't match the registered user
// This is problematic when Kamailio routes calls using DDI numbers instead of usernames
// Fix: Override receiveRequest to skip the R-URI user validation
(() => {
  const originalReceiveRequest = (UA.prototype as any).receiveRequest;
  if (originalReceiveRequest && !(UA.prototype as any)._patchedForAcceptAll) {
    (UA.prototype as any).receiveRequest = function(request: any) {
      // Temporarily set ruri.user to match our configured user to bypass the check
      // This allows accepting calls addressed to any user (DDI, extension, etc.)
      const originalRuriUser = request.ruri?.user;
      if (request.ruri && this._configuration?.uri?.user) {
        request.ruri.user = this._configuration.uri.user;
      }

      // Call original method
      const result = originalReceiveRequest.call(this, request);

      // Restore original ruri.user for logging/debugging purposes
      if (request.ruri && originalRuriUser !== undefined) {
        request.ruri.user = originalRuriUser;
      }

      return result;
    };
    (UA.prototype as any)._patchedForAcceptAll = true;
    console.log('[JsSIP] Patched to accept all incoming calls (R-URI user check bypassed)');
  }
})();

interface WebRtcCredentials {
  sipUser: string;
  sipPassword: string;
  domain: string;
  displayName: string;
  wsServer: string;
  stunServers: string[];
  turnServers: { urls: string; username: string; credential: string }[];
}

interface UseJsSipClientReturn {
  registrationState: 'unregistered' | 'registering' | 'registered' | 'error';
  callState: 'idle' | 'calling' | 'ringing' | 'active' | 'held';
  error: string | null;
  register: (credentials: WebRtcCredentials) => void;
  unregister: () => void;
  call: (destination: string) => void;
  hangup: () => void;
  answer: () => void;
  hold: () => void;
  unhold: () => void;
  mute: () => void;
  unmute: () => void;
  sendDtmf: (digit: string) => void;
}

// Diagnostic timing helper
const logWithTime = (message: string, ...args: unknown[]) => {
  const now = new Date();
  const timestamp = now.toISOString().split('T')[1].replace('Z', '');
  console.log(`[JsSIP ${timestamp}] ${message}`, ...args);
};

export const useJsSipClient = (): UseJsSipClientReturn => {
  const uaRef = useRef<UA | null>(null);
  const sessionRef = useRef<RTCSession | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const credentialsRef = useRef<WebRtcCredentials | null>(null);
  const callStartTimeRef = useRef<number | null>(null);

  const [registrationState, setRegistrationState] = useState<'unregistered' | 'registering' | 'registered' | 'error'>('unregistered');
  const [callState, setCallState] = useState<'idle' | 'calling' | 'ringing' | 'active' | 'held'>('idle');
  const [error, setError] = useState<string | null>(null);

  // Create audio element for remote audio
  useEffect(() => {
    audioRef.current = new Audio();
    audioRef.current.autoplay = true;
    return () => {
      audioRef.current?.pause();
      audioRef.current = null;
    };
  }, []);

  // Unregister on page unload/refresh to prevent orphaned registrations
  useEffect(() => {
    const handleBeforeUnload = () => {
      if (uaRef.current) {
        logWithTime('Page unloading - unregistering');
        try {
          uaRef.current.unregister();
          uaRef.current.stop();
        } catch (e) {
          // Ignore errors during cleanup
        }
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, []);

  // Setup session event handlers (ICE handling is in call() eventHandlers.peerconnection)
  const setupSessionHandlers = useCallback((session: RTCSession) => {
    session.on('sending', (e: { request: unknown }) => {
      const elapsed = callStartTimeRef.current
        ? ((Date.now() - callStartTimeRef.current) / 1000).toFixed(2)
        : '?';
      logWithTime(`INVITE sent [${elapsed}s]`);
    });

    session.on('progress', (e: { originator?: string; response?: unknown }) => {
      logWithTime('Ringing');
      setCallState('calling');
    });

    session.on('accepted', (e: { originator?: string }) => {
      logWithTime('Call accepted');
    });

    session.on('confirmed', () => {
      logWithTime('Call connected');
      setCallState('active');
      // Attach remote audio stream
      const remoteStream = session.connection?.getRemoteStreams()[0];
      if (remoteStream && audioRef.current) {
        audioRef.current.srcObject = remoteStream;
      }
    });

    session.on('ended', (e: { originator?: string; cause?: string }) => {
      logWithTime(`Call ended: ${e.cause || 'normal'}`);
      setCallState('idle');
      sessionRef.current = null;
      callStartTimeRef.current = null;
    });

    session.on('failed', (e: { originator?: string; cause?: string; message?: { status_code?: number } }) => {
      logWithTime(`Call failed: ${e.cause || 'unknown'}${e.message?.status_code ? ` (${e.message.status_code})` : ''}`);
      setCallState('idle');
      setError(e.cause || 'Call failed');
      sessionRef.current = null;
      callStartTimeRef.current = null;
    });

    session.on('getusermediafailed', (e: DOMException) => {
      logWithTime(`Microphone error: ${e.message}`);
      setCallState('idle');
      setError(`Microphone access denied: ${e.message}`);
      sessionRef.current = null;
      callStartTimeRef.current = null;
    });

    session.on('hold', () => {
      setCallState('held');
    });

    session.on('unhold', () => {
      setCallState('active');
    });
  }, []);

  const register = useCallback((credentials: WebRtcCredentials) => {
    try {
      // IMPORTANT: Cleanup any existing UA before creating a new one
      // This prevents duplicate registrations when auto-register fires multiple times
      if (uaRef.current) {
        logWithTime('Cleaning up existing UA before new registration');
        try {
          uaRef.current.unregister();
          uaRef.current.stop();
        } catch (e) {
          // Ignore errors during cleanup
        }
        uaRef.current = null;
      }

      setRegistrationState('registering');
      setError(null);
      credentialsRef.current = credentials;

      const socket = new WebSocketInterface(credentials.wsServer);

      const configuration: UAConfiguration = {
        sockets: [socket],
        uri: `sip:${credentials.sipUser}@${credentials.domain}`,
        password: credentials.sipPassword,
        display_name: credentials.displayName,
        register: true,
        session_timers: false
      };

      const ua = new UA(configuration);
      uaRef.current = ua;

      // Registration events
      ua.on('registered', () => {
        setRegistrationState('registered');
      });

      ua.on('unregistered', () => {
        setRegistrationState('unregistered');
      });

      ua.on('registrationFailed', (e: { cause?: string }) => {
        setRegistrationState('error');
        setError(e.cause || 'Registration failed');
      });

      // Incoming call handling
      ua.on('newRTCSession', (e: IncomingRTCSessionEvent | OutgoingRTCSessionEvent) => {
        const session = e.session;

        if (session.direction === 'incoming') {
          setCallState('ringing');
          sessionRef.current = session;
          setupSessionHandlers(session);

          // For MVP, auto-setup handlers; UI will call answer() or hangup()
        }
      });

      ua.start();
    } catch (err) {
      setRegistrationState('error');
      setError(err instanceof Error ? err.message : 'Registration failed');
    }
  }, [setupSessionHandlers]);

  const unregister = useCallback(() => {
    uaRef.current?.unregister();
    uaRef.current?.stop();
    uaRef.current = null;
    setRegistrationState('unregistered');
  }, []);

  const call = useCallback((destination: string) => {
    if (!uaRef.current || !credentialsRef.current) {
      logWithTime('Cannot call - not registered');
      setError('Not registered');
      return;
    }

    callStartTimeRef.current = Date.now();
    logWithTime(`Calling ${destination}`);

    // Track ICE gathering timing
    const iceGatherStartTime = Date.now();
    let candidateCount = 0;
    let hostCandidates = 0;
    let srflxCandidates = 0;
    let relayCandidates = 0;
    let hasSignaledComplete = false;
    let hasStartedTimeout = false;

    // ICE gathering timeout - once we have SRFLX or RELAY candidates, we can proceed
    // without waiting for all candidates (avoids 40s IPv6 STUN/TURN timeout)
    // - SRFLX (STUN) handles ~80-90% of NAT scenarios
    // - RELAY (TURN) handles remaining symmetric NAT cases
    const ICE_GATHERING_TIMEOUT_MS = 2000; // 2 seconds after first usable candidate

    const options: CallOptions = {
      mediaConstraints: { audio: true, video: false },
      pcConfig: {
        iceServers: [
          ...credentialsRef.current.stunServers.map(url => ({ urls: url })),
          ...credentialsRef.current.turnServers.map(turn => ({
            urls: turn.urls,
            username: turn.username,
            credential: turn.credential
          }))
        ],
        // Pre-gather some candidates (can speed up call setup)
        iceCandidatePoolSize: 2
      },
      // Add event target to catch errors early - this fires DURING ua.call()
      eventHandlers: {
        peerconnection: (e: { peerconnection: RTCPeerConnection }) => {
          logWithTime('PeerConnection created');
          const pc = e.peerconnection;
          let iceGatheringTimer: ReturnType<typeof setTimeout> | null = null;

          // Function to signal that we have enough candidates to proceed
          const signalGatheringComplete = () => {
            if (hasSignaledComplete) return;
            hasSignaledComplete = true;
            if (iceGatheringTimer) {
              clearTimeout(iceGatheringTimer);
              iceGatheringTimer = null;
            }
            const elapsed = ((Date.now() - iceGatherStartTime) / 1000).toFixed(2);
            logWithTime(`ICE forced complete [${elapsed}s]: ${candidateCount} candidates (host:${hostCandidates}, srflx:${srflxCandidates}, relay:${relayCandidates})`);
            // Dispatch a null candidate to signal end-of-candidates to JsSIP
            // This is a workaround for IPv6 STUN/TURN timeout issues
            const originalOnIceCandidate = pc.onicecandidate;
            pc.onicecandidate = null;
            pc.dispatchEvent(new RTCPeerConnectionIceEvent('icecandidate', { candidate: null }));
            pc.onicecandidate = originalOnIceCandidate;
          };

          // Helper to start the ICE gathering timeout
          const maybeStartTimeout = (candidateType: string) => {
            if (!hasStartedTimeout && !iceGatheringTimer && !hasSignaledComplete) {
              hasStartedTimeout = true;
              logWithTime(`First ${candidateType} candidate - starting ${ICE_GATHERING_TIMEOUT_MS}ms timeout`);
              iceGatheringTimer = setTimeout(() => {
                if (pc.iceGatheringState !== 'complete') {
                  logWithTime('ICE timeout - proceeding with available candidates');
                  signalGatheringComplete();
                }
              }, ICE_GATHERING_TIMEOUT_MS);
            }
          };

          pc.onicecandidate = (event) => {
            if (event.candidate) {
              candidateCount++;
              const candidate = event.candidate.candidate;
              // Parse candidate type and count
              if (candidate.includes('typ host')) {
                hostCandidates++;
              } else if (candidate.includes('typ srflx')) {
                srflxCandidates++;
                // Start timeout on first SRFLX - STUN is working, handles most NAT types
                maybeStartTimeout('SRFLX');
              } else if (candidate.includes('typ relay')) {
                relayCandidates++;
                // Start timeout on first RELAY if not already started by SRFLX
                maybeStartTimeout('RELAY');
              }
            } else {
              // Gathering complete (null candidate)
              if (iceGatheringTimer) {
                clearTimeout(iceGatheringTimer);
                iceGatheringTimer = null;
              }
              hasSignaledComplete = true;
              const elapsed = ((Date.now() - iceGatherStartTime) / 1000).toFixed(2);
              logWithTime(`ICE complete [${elapsed}s]: ${candidateCount} candidates (host:${hostCandidates}, srflx:${srflxCandidates}, relay:${relayCandidates})`);
            }
          };

          pc.oniceconnectionstatechange = () => {
            if (pc.iceConnectionState === 'failed') {
              logWithTime('ICE connection failed');
              setError('ICE connection failed - check network/TURN server');
            } else if (pc.iceConnectionState === 'connected') {
              logWithTime('ICE connected');
            }
          };

          pc.onicegatheringstatechange = () => {
            // Only log state changes, main logging is in onicecandidate
          };

          pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'connected') {
              logWithTime('Media connected');
            } else if (pc.connectionState === 'failed') {
              logWithTime('Connection failed');
            }
          };

          pc.onsignalingstatechange = () => {
            // Signaling state changes are handled by JsSIP
          };
        },
        failed: (e: { cause?: string }) => {
          logWithTime('Call failed (from options):', e.cause);
        },
        getusermediafailed: (e: DOMException) => {
          logWithTime('getUserMedia FAILED:', e.name, e.message);
          setCallState('idle');
          setError(`Microphone access denied: ${e.message}`);
        }
      }
    };

    try {
      const sipUri = `sip:${destination}@${credentialsRef.current.domain}`;
      const session = uaRef.current.call(sipUri, options);

      if (!session) {
        logWithTime('Failed to create session');
        setError('Failed to create call session');
        return;
      }

      sessionRef.current = session;
      setupSessionHandlers(session);
      setCallState('calling');
    } catch (err) {
      logWithTime('Call error:', err);
      setError(err instanceof Error ? err.message : 'Call failed');
      setCallState('idle');
      callStartTimeRef.current = null;
    }
  }, [setupSessionHandlers]);

  const hangup = useCallback(() => {
    sessionRef.current?.terminate();
    sessionRef.current = null;
    setCallState('idle');
  }, []);

  const answer = useCallback(() => {
    if (sessionRef.current && sessionRef.current.direction === 'incoming') {
      const options: AnswerOptions = {
        mediaConstraints: { audio: true, video: false },
        pcConfig: credentialsRef.current ? {
          iceServers: [
            ...credentialsRef.current.stunServers.map(url => ({ urls: url })),
            ...credentialsRef.current.turnServers.map(turn => ({
              urls: turn.urls,
              username: turn.username,
              credential: turn.credential
            }))
          ]
        } : undefined
      };
      sessionRef.current.answer(options);
    }
  }, []);

  const hold = useCallback(() => {
    sessionRef.current?.hold();
  }, []);

  const unhold = useCallback(() => {
    sessionRef.current?.unhold();
  }, []);

  const mute = useCallback(() => {
    sessionRef.current?.mute();
  }, []);

  const unmute = useCallback(() => {
    sessionRef.current?.unmute();
  }, []);

  const sendDtmf = useCallback((digit: string) => {
    sessionRef.current?.sendDTMF(digit);
  }, []);

  return {
    registrationState,
    callState,
    error,
    register,
    unregister,
    call,
    hangup,
    answer,
    hold,
    unhold,
    mute,
    unmute,
    sendDtmf
  };
};
