<?php

namespace App\EventListener;

use App\Event\OrderStatusChangedEvent;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStatusChangedListener implements EventSubscriberInterface
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderStatusChangedEvent::class => 'onOrderStatusChanged',
        ];
    }

    public function onOrderStatusChanged(OrderStatusChangedEvent $event): void
    {
        $order = $event->getOrder();
        $newStatus = $event->getNewStatus();
        $oldStatus = $event->getOldStatus();

        // Mise à jour du stock lorsque la commande passe en "En cours"
        if ($newStatus === 'En cours' && $oldStatus !== 'En cours') {
            foreach ($order->getItems() as $orderItem) {
                $product = $orderItem->getProduct();
                $newStock = $product->getStock() - $orderItem->getQuantity();
                
                // Vérifier si le stock ne devient pas négatif
                if ($newStock < 0) {
                    throw new \RuntimeException(sprintf(
                        'Stock insuffisant pour le produit "%s". Stock disponible : %d, Quantité demandée : %d',
                        $product->getName(),
                        $product->getStock(),
                        $orderItem->getQuantity()
                    ));
                }
                
                $product->setStock($newStock);
            }
            
            // Sauvegarder les modifications
            $this->entityManager->flush();
        }
        // Remettre en stock si la commande est annulée
        elseif ($newStatus === 'Annulé' && $oldStatus === 'En cours') {
            foreach ($order->getItems() as $orderItem) {
                $product = $orderItem->getProduct();
                $newStock = $product->getStock() + $orderItem->getQuantity();
                $product->setStock($newStock);
            }
            
            // Sauvegarder les modifications
            $this->entityManager->flush();
        }

        // Envoyer l'email de notification
        $this->emailService->sendOrderStatusUpdate(
            $order->getUser()->getEmail(),
            [
                'order' => $order,
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus
            ]
        );
    }
} 