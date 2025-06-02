<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Event\OrderStatusChangedEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Route('/admin/orders')]
class OrderController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('/', name: 'admin_orders_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/orders/index.html.twig', [
            'orders' => $this->orderRepository->findBy([], ['orderDate' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'admin_orders_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_orders_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Order $order): Response
    {
        if ($this->isCsrfTokenValid('update'.$order->getId(), $request->request->get('_token'))) {
            $newStatus = $request->request->get('status');
            if (in_array($newStatus, ['En attente', 'En cours', 'Expédié', 'Livré', 'Annulé'])) {
                $oldStatus = $order->getStatus();
                $order->setStatus($newStatus);
                
                // Déclencher l'événement de changement de statut
                $event = new OrderStatusChangedEvent($order, $oldStatus, $newStatus);
                $this->eventDispatcher->dispatch($event);
                
                $this->entityManager->flush();
                $this->addFlash('success', 'Le statut de la commande a été mis à jour avec succès.');
            }
        }

        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_orders_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($order);
            $this->entityManager->flush();
            $this->addFlash('success', 'La commande a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_orders_index');
    }
} 