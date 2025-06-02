<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Form\ProductType;
use App\Repository\AdminRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(OrderRepository $orderRepository, AdminRepository $adminRepository, ProductRepository $productRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'orders' => $orderRepository->findBy([], ['orderDate' => 'DESC']),
            'users' => $adminRepository->findAll(),
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/products', name: 'app_admin_products')]
    public function products(ProductRepository $productRepository): Response
    {
        return $this->render('admin/products/index.html.twig', [
            'products' => $productRepository->findBy(['isDeleted' => false], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/products/new', name: 'app_admin_product_new')]
    public function newProduct(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageFiles = $form->get('productImages')->getData();
                
                if ($imageFiles) {
                    foreach ($imageFiles as $imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                        try {
                            $imageFile->move(
                                $this->getParameter('products_directory'),
                                $newFilename
                            );

                            $productImage = new ProductImage();
                            $productImage->setFilename($newFilename);
                            $product->addProductImage($productImage);
                            
                        } catch (FileException $e) {
                            $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image');
                            return $this->redirectToRoute('app_admin_product_new');
                        }
                    }
                }

                $entityManager->persist($product);
                $entityManager->flush();

                $this->addFlash('success', 'Le produit a été ajouté avec succès');
                return $this->redirectToRoute('app_admin_products');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout du produit');
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product
        ]);
    }

    #[Route('/products/{id}/edit', name: 'app_admin_product_edit')]
    public function editProduct(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageFiles = $form->get('productImages')->getData();
                
                if ($imageFiles) {
                    foreach ($imageFiles as $imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                        try {
                            $imageFile->move(
                                $this->getParameter('products_directory'),
                                $newFilename
                            );

                            $productImage = new ProductImage();
                            $productImage->setFilename($newFilename);
                            $product->addProductImage($productImage);
                            
                        } catch (FileException $e) {
                            $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image');
                            return $this->redirectToRoute('app_admin_product_edit', ['id' => $product->getId()]);
                        }
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Le produit a été modifié avec succès');
                return $this->redirectToRoute('app_admin_products');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification du produit');
            }
        }

        return $this->render('admin/products/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(AdminRepository $adminRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $adminRepository->findAll(),
        ]);
    }

    #[Route('/user/{id}', name: 'app_admin_user_show')]
    public function showUser($id, AdminRepository $adminRepository): Response
    {
        $user = $adminRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        return $this->render('admin/user_show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'app_admin_product_delete', methods: ['POST'])]
    public function deleteProduct(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            try {
                $product->setIsDeleted(true);
                $entityManager->flush();
                $this->addFlash('success', 'Le produit a été supprimé avec succès');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression du produit');
            }
        }

        return $this->redirectToRoute('app_admin_products');
    }
} 