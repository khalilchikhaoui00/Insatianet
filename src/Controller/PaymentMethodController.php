<?php

namespace App\Controller;

use App\Entity\PaymentMethod;
use App\Form\PaymentMethodType;
use App\Repository\PaymentMethodRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment-method')]
#[IsGranted('ROLE_USER')]
class PaymentMethodController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService
    ) {
    }

    #[Route('/', name: 'app_payment_method_index', methods: ['GET'])]
    public function index(PaymentMethodRepository $paymentMethodRepository): Response
    {
        return $this->render('payment_method/index.html.twig', [
            'payment_methods' => $paymentMethodRepository->findBy(['user' => $this->getUser()]),
        ]);
    }

    #[Route('/new', name: 'app_payment_method_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setUser($this->getUser());
        
        // Si c'est la première carte, la définir par défaut
        if (count($this->getUser()->getPaymentMethods()) === 0) {
            $paymentMethod->setIsDefault(true);
        }

        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Détecter la marque de la carte
            $cardNumber = $paymentMethod->getCardNumber();
            $brand = $this->detectCardBrand($cardNumber);
            $paymentMethod->setBrand($brand);

            // Si cette carte est définie par défaut, retirer le statut par défaut des autres
            if ($paymentMethod->isDefault()) {
                $this->removeDefaultFromOtherCards($this->getUser()->getId());
            }

            $this->entityManager->persist($paymentMethod);
            $this->entityManager->flush();

            // Envoyer l'email de confirmation
            $this->emailService->sendPaymentMethodConfirmation(
                $this->getUser()->getEmail(),
                [
                    'user' => $this->getUser(),
                    'paymentMethod' => $paymentMethod
                ]
            );

            $this->addFlash('success', 'Votre carte a été ajoutée avec succès. Un email de confirmation vous a été envoyé.');
            return $this->redirectToRoute('app_payment_method_index');
        }

        return $this->render('payment_method/new.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_method_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaymentMethod $paymentMethod): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la carte
        if ($paymentMethod->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si cette carte est définie par défaut, retirer le statut par défaut des autres
            if ($paymentMethod->isDefault()) {
                $this->removeDefaultFromOtherCards($this->getUser()->getId(), $paymentMethod->getId());
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre carte a été mise à jour avec succès.');
            return $this->redirectToRoute('app_payment_method_index');
        }

        return $this->render('payment_method/edit.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_payment_method_delete', methods: ['POST'])]
    public function delete(Request $request, PaymentMethod $paymentMethod): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la carte
        if ($paymentMethod->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$paymentMethod->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($paymentMethod);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre carte a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_payment_method_index');
    }

    private function detectCardBrand(string $cardNumber): string
    {
        // Détection simplifiée des marques de carte
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

    private function removeDefaultFromOtherCards(int $userId, ?int $excludeId = null): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(PaymentMethod::class, 'p')
           ->set('p.isDefault', ':false')
           ->where('p.user = :userId')
           ->setParameter('false', false)
           ->setParameter('userId', $userId);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $qb->getQuery()->execute();
    }
} 