<?php

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ClientFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create default admin client
        $clientRepository = $manager->getRepository(Client::class);
        $adminClient = $clientRepository->findOneBy(['email' => 'admin@admin.com']);

        if (!$adminClient) {
            $adminClient = new Client();
            $adminClient->setEmail('admin@admin.com');
            $adminClient->setFirstName('System');
            $adminClient->setLastName('Administrator');
            $adminClient->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $adminClient->setIsVerified(true);

            $hashedPassword = $this->passwordHasher->hashPassword(
                $adminClient,
                'admin123'
            );
            $adminClient->setPassword($hashedPassword);

            $manager->persist($adminClient);
        }

        // Create default regular client
        $regularClient = $clientRepository->findOneBy(['email' => 'client@example.com']);

        if (!$regularClient) {
            $regularClient = new Client();
            $regularClient->setEmail('client@example.com');
            $regularClient->setFirstName('John');
            $regularClient->setLastName('Doe');
            $regularClient->setRoles(['ROLE_USER']);
            $regularClient->setIsVerified(true);

            $hashedPassword = $this->passwordHasher->hashPassword(
                $regularClient,
                'client123'
            );
            $regularClient->setPassword($hashedPassword);

            $manager->persist($regularClient);
        }

        $manager->flush();
    }
} 