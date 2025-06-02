<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\PaymentMethod;
use App\Form\OrderTypeForm;
use App\Service\EmailService;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Repository\PaymentMethodRepository;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private EmailService $emailService,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    #[Route('/', name: 'app_order_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $order = new Order();
        $order->setUser($this->getUser());
        $order->setOrderDate(new \DateTimeImmutable());
        $order->setStatus('En attente');
        
        // Récupérer le montant total du panier
        $totalAmount = $this->getCartTotal();
        $order->setTotalAmount((string)$totalAmount);

        $form = $this->createForm(OrderTypeForm::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Traitement de la commande
                $this->entityManager->persist($order);
                $this->entityManager->flush();

                // Envoi de l'email de confirmation
                $this->emailService->sendOrderConfirmation(
                    $this->getUser()->getEmail(),
                    [
                        'id' => $order->getId(),
                        'orderDate' => $order->getOrderDate(),
                        'totalAmount' => $order->getTotalAmount(),
                        'status' => $order->getStatus(),
                        'shippingAddress' => $order->getShippingAddress()
                    ]
                );

                $this->addFlash('success', 'Votre commande a été enregistrée et un email de confirmation vous a été envoyé !');
                return $this->redirectToRoute('app_home');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue l\'envoi de l\'email : ' . $e->getMessage());
            }
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_order_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Order $order): Response
    {
        // Vérifier que l'utilisateur est le propriétaire de la commande
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/order/confirm/{id}', name: 'app_order_confirm')]
    public function confirm(Order $order): Response
    {
        // Mettre à jour le statut de la commande
        $order->setStatus('confirmed');
        $this->entityManager->flush();

        // Envoyer l'email de confirmation
        $this->emailService->sendOrderConfirmation(
            $order->getUser()->getEmail(),
            [
                'id' => $order->getId(),
                'orderDate' => $order->getOrderDate(),
                'totalAmount' => $order->getTotalAmount(),
                'status' => $order->getStatus(),
                'shippingAddress' => $order->getShippingAddress()
            ]
        );

        $this->addFlash('success', 'Votre commande a été confirmée. Un email de confirmation vous a été envoyé.');

        return $this->redirectToRoute('app_order_history');
    }

    #[Route('/create', name: 'app_order_create', methods: ['POST'])]
    public function create(
        Request $request,
        SessionInterface $session,
        ProductRepository $productRepository
    ): Response {
        try {
            $this->denyAccessUnlessGranted('ROLE_USER');

            $cart = $session->get('cart', []);
            if (empty($cart)) {
                throw new \Exception('Votre panier est vide');
            }

            // Vérifier l'adresse de livraison
            $shippingAddress = $request->request->get('shippingAddress');
            if (empty($shippingAddress)) {
                throw new \Exception('L\'adresse de livraison est requise');
            }

            // Créer la commande
            $order = new Order();
            $order->setUser($this->getUser());
            $order->setOrderDate(new \DateTimeImmutable());
            $order->setStatus('En attente');
            $order->setShippingAddress($shippingAddress);

            // Gérer le paiement
            $paymentType = $request->request->get('paymentType');
            if (empty($paymentType)) {
                throw new \Exception('Le mode de paiement est requis');
            }

            if (str_starts_with($paymentType, 'saved_')) {
                // Utiliser une carte enregistrée
                $paymentMethodId = (int) substr($paymentType, 6);
                $paymentMethod = $this->paymentMethodRepository->find($paymentMethodId);
                
                if (!$paymentMethod || $paymentMethod->getUser() !== $this->getUser()) {
                    throw new \Exception('Carte de paiement invalide');
                }

                $order->setCardNumber($paymentMethod->getCardNumber());
                $order->setCardExpiry($paymentMethod->getExpiryDate());
                $order->setCardCvv($paymentMethod->getCvc());
            } else {
                // Nouvelle carte
                $cardNumber = $request->request->get('cardNumber');
                $cardExpiry = $request->request->get('cardExpiry');
                $cardCvv = $request->request->get('cardCvv');

                // Validation des données de carte
                if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
                    throw new \Exception('Toutes les informations de carte sont requises');
                }

                if (!preg_match('/^\d{16}$/', $cardNumber) || 
                    !preg_match('/^\d{2}\/\d{2}$/', $cardExpiry) || 
                    !preg_match('/^\d{3}$/', $cardCvv)) {
                    throw new \Exception('Informations de carte invalides');
                }

                $order->setCardNumber($cardNumber);
                $order->setCardExpiry($cardExpiry);
                $order->setCardCvv($cardCvv);

                // Sauvegarder la carte si demandé
                if ($request->request->get('saveCard')) {
                    $paymentMethod = new PaymentMethod();
                    $paymentMethod->setUser($this->getUser());
                    $paymentMethod->setCardNumber($cardNumber);
                    $paymentMethod->setExpiryDate($cardExpiry);
                    $paymentMethod->setCvc($cardCvv);
                    $paymentMethod->setCardholderName($this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName());
                    $paymentMethod->setBrand($this->detectCardBrand($cardNumber));
                    
                    // Si c'est la première carte, la définir par défaut
                    if (count($this->getUser()->getPaymentMethods()) === 0) {
                        $paymentMethod->setIsDefault(true);
                    }

                    $this->entityManager->persist($paymentMethod);
                }
            }

            // Créer les items de la commande et calculer le total
            $total = 0;
            foreach ($cart as $id => $quantity) {
                $product = $productRepository->find($id);
                if (!$product) {
                    throw new \Exception('Un des produits de votre panier n\'existe plus');
                }

                if ($quantity > $product->getStock()) {
                    throw new \Exception(sprintf(
                        'Le produit "%s" n\'a plus que %d unités en stock',
                        $product->getName(),
                        $product->getStock()
                    ));
                }

                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setPrice($product->getPrice());
                
                // Appliquer la promotion si elle existe
                if ($product->getPromotion() > 0) {
                    $orderItem->setPrice($product->getPriceAfterPromotion());
                }
                
                $order->addItem($orderItem);
                $total += $orderItem->getPrice() * $quantity;

                // Mettre à jour le stock
                $product->setStock($product->getStock() - $quantity);
                $this->entityManager->persist($product);
            }

            $order->setTotalAmount((string) $total);

            // Persister la commande et ses items
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // Vider le panier
            $session->remove('cart');

            // Envoyer l'email de confirmation
            try {
                $this->emailService->sendOrderConfirmation(
                    $this->getUser()->getEmail(),
                    [
                        'order' => $order,
                        'items' => $order->getItems()
                    ]
                );
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas interrompre le processus
                // TODO: Ajouter un logger approprié
            }

            $this->addFlash('success', 'Votre commande a été créée avec succès !');
            return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la création de votre commande : ' . $e->getMessage());
            return $this->redirectToRoute('app_cart_checkout');
        }
    }

    #[Route('/confirmation/{id}', name: 'app_order_confirmation')]
    public function confirmation(Order $order): Response
    {
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('order/confirmation.html.twig', [
            'order' => $order
        ]);
    }

    private function detectCardBrand(string $cardNumber): string
    {
        if (str_starts_with($cardNumber, '4')) {
            return 'Visa';
        } elseif (str_starts_with($cardNumber, '5')) {
            return 'MasterCard';
        } elseif (str_starts_with($cardNumber, '34') || str_starts_with($cardNumber, '37')) {
            return 'American Express';
        } else {
            return 'Autre';
        }
    }

    private function getCartTotal(): float
    {
        // À implémenter selon votre logique de panier
        // Pour l'exemple, nous retournons une valeur fixe
        return 99.99;
    }
}
