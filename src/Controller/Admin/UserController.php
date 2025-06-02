<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    private const ADMIN_EMAIL = 'admin@admin.com';

    #[Route('/', name: 'admin_users_index', methods: ['GET'])]
    public function index(ClientRepository $clientRepository): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $clientRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'admin_users_show', methods: ['GET'])]
    public function show(Client $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/toggle-role', name: 'admin_users_toggle_role', methods: ['POST'])]
    public function toggleRole(Client $user, EntityManagerInterface $entityManager): Response
    {
        if ($user->getEmail() === self::ADMIN_EMAIL) {
            $this->addFlash('error', 'Impossible de modifier les rôles du compte administrateur principal.');
            return $this->redirectToRoute('admin_users_index');
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles)) {
            $roles = array_diff($roles, ['ROLE_ADMIN']);
        } else {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles(array_values(array_unique($roles)));
        
        $entityManager->flush();

        $this->addFlash('success', 'Les rôles de l\'utilisateur ont été mis à jour avec succès.');
        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, Client $user, EntityManagerInterface $entityManager): Response
    {
        if ($user->getEmail() === self::ADMIN_EMAIL) {
            $this->addFlash('error', 'Impossible de supprimer le compte administrateur principal.');
            return $this->redirectToRoute('admin_users_index');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_users_index');
    }
} 