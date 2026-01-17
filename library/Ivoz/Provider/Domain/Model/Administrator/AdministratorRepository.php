<?php

namespace Ivoz\Provider\Domain\Model\Administrator;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;

interface AdministratorRepository extends ObjectRepository, Selectable
{
    /**
     * @return AdministratorInterface
     * @throws \RuntimeException
     */
    public function getInnerGlobalAdmin();

    public function findAdminByUsername(string $username): ?AdministratorInterface;

    /**
     * @param string $username
     * @return null| AdministratorInterface
     */
    public function findPlatformAdminByUsername(string $username);

    /**
     * @param string $username
     * @return null| AdministratorInterface
     */
    public function findBrandAdminByUsername(string $username);

    /**
     * @param string $username
     * @return null| AdministratorInterface
     */
    public function findClientAdminByUsername(string $username);

    /**
     * Find the first active administrator for a company
     *
     * @param CompanyInterface $company
     * @return AdministratorInterface|null
     */
    public function findFirstActiveByCompany(CompanyInterface $company): ?AdministratorInterface;
}
