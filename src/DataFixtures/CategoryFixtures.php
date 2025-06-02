<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            'Ordinateurs portables',
            'Ordinateurs de bureau',
            'Composants PC',
            'Périphériques',
            'Réseau',
            'Stockage',
            'Gaming',
            'Logiciels'
        ];

        foreach ($categories as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
        }

        $manager->flush();
    }
} 