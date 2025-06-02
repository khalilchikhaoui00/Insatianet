<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/products')]
#[IsGranted('ROLE_ADMIN')]
class AdminProductController extends AbstractController
{
    private ProductRepository $productRepository;
    private EntityManagerInterface $entityManager;
    private string $uploadDir;

    public function __construct(
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        string $uploadDir = 'uploads/products'
    ) {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
        $this->uploadDir = $uploadDir;
    }

    #[Route('/', name: 'app_admin_products')]
    public function index(): Response
    {
        return $this->render('admin/products/index.html.twig', [
            'products' => $this->productRepository->findBy(['isDeleted' => false], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gérer les images
                $imageFiles = $form->get('productImages')->getData();
                if ($imageFiles) {
                    foreach ($imageFiles as $imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                        $imageFile->move(
                            $this->getParameter('product_images_directory'),
                            $newFilename
                        );

                        $productImage = new ProductImage();
                        $productImage->setFilename($newFilename);
                        $product->addProductImage($productImage);
                    }
                }

                $this->entityManager->persist($product);
                $this->entityManager->flush();

                $this->addFlash('success', 'Le produit a été créé avec succès.');
                return $this->redirectToRoute('app_admin_products');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du produit : ' . $e->getMessage());
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gérer les images
                $imageFiles = $form->get('productImages')->getData();
                if ($imageFiles) {
                    foreach ($imageFiles as $imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                        $imageFile->move(
                            $this->getParameter('product_images_directory'),
                            $newFilename
                        );

                        $productImage = new ProductImage();
                        $productImage->setFilename($newFilename);
                        $product->addProductImage($productImage);
                    }
                }

                $this->entityManager->flush();

                $this->addFlash('success', 'Le produit a été modifié avec succès.');
                return $this->redirectToRoute('app_admin_products');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification du produit : ' . $e->getMessage());
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_products_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            try {
                $product->setIsDeleted(true);
                $this->entityManager->flush();
                $this->addFlash('success', 'Le produit a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression du produit.');
            }
        }

        return $this->redirectToRoute('app_admin_products');
    }
} 