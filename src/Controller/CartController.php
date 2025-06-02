<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    #[Route('/', name: 'app_cart')]
    public function index(SessionInterface $session, ProductRepository $productRepository): Response
    {
        $cart = $session->get('cart', []);
        $cartData = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $product = $productRepository->find($id);
            if ($product) {
                $cartData[] = [
                    'product' => $product,
                    'quantity' => $quantity
                ];
                $total += $product->getPrice() * $quantity;
            }
        }

        return $this->render('cart/index.html.twig', [
            'items' => $cartData,
            'total' => $total
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add($id, Request $request, SessionInterface $session, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute('app_cart');
        }

        // Get quantity from form submission or default to 1
        $quantity = (int) $request->request->get('quantity', 1);
        
        // Validate quantity
        if ($quantity < 1) {
            $quantity = 1;
        }
        if ($quantity > $product->getStock()) {
            $quantity = $product->getStock();
            $this->addFlash('warning', 'La quantité a été ajustée au stock disponible');
        }

        $cart = $session->get('cart', []);
        
        if (!empty($cart[$id])) {
            // Add the new quantity to existing quantity
            $cart[$id] += $quantity;
            // Make sure we don't exceed stock
            if ($cart[$id] > $product->getStock()) {
                $cart[$id] = $product->getStock();
                $this->addFlash('warning', 'La quantité a été ajustée au stock disponible');
            }
        } else {
            $cart[$id] = $quantity;
        }
        
        $session->set('cart', $cart);
        $this->addFlash('success', 'Produit ajouté au panier');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove')]
    public function remove($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        
        if (!empty($cart[$id])) {
            unset($cart[$id]);
        }
        
        $session->set('cart', $cart);
        $this->addFlash('success', 'Produit retiré du panier');

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/update-quantity/{id}', name: 'app_cart_update_quantity', methods: ['POST'])]
    public function updateQuantity($id, Request $request, SessionInterface $session, ProductRepository $productRepository): Response
    {
        $quantity = (int) $request->request->get('quantity');
        if ($quantity > 0) {
            $product = $productRepository->find($id);
            if ($product) {
                // Make sure we don't exceed stock
                if ($quantity > $product->getStock()) {
                    $quantity = $product->getStock();
                    $this->addFlash('warning', 'La quantité a été ajustée au stock disponible');
                }
                $cart = $session->get('cart', []);
                $cart[$id] = $quantity;
                $session->set('cart', $cart);
            }
        }
        
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_cart_checkout')]
    public function checkout(SessionInterface $session, ProductRepository $productRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $cart = $session->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart');
        }

        $total = 0;
        foreach ($cart as $id => $quantity) {
            $product = $productRepository->find($id);
            if ($product) {
                $total += $product->getPrice() * $quantity;
            }
        }

        return $this->render('cart/checkout.html.twig', [
            'total' => $total
        ]);
    }
} 