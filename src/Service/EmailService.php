<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

class EmailService
{
    // Remplacez par votre adresse Gmail
    private const SENDER_EMAIL = 'khalilchikhaoui00@gmail.com';
    private const SENDER_NAME = 'Insatianet - Ne pas répondre';

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $adminEmail,
        private \Twig\Environment $twig
    ) {
    }

    public function send(TemplatedEmail $email): void
    {
        if (!$email->getFrom()) {
            $email->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME));
        }
        
        try {
            $this->logger->info('Tentative d\'envoi d\'email - Détails complets', [
                'from' => $email->getFrom(),
                'to' => $email->getTo(),
                'subject' => $email->getSubject(),
                'template' => $email->getHtmlTemplate(),
                'headers' => $email->getHeaders()->toString(),
                'sender_email' => self::SENDER_EMAIL,
                'sender_name' => self::SENDER_NAME
            ]);
            
            $this->mailer->send($email);
            
            $this->logger->info('Email envoyé avec succès');
        } catch (\Exception $e) {
            $this->logger->error('Erreur détaillée lors de l\'envoi de l\'email', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function sendOrderConfirmation(string $to, array $orderData): void
    {
        try {
            $order = $orderData['order'];
            
            // Générer le PDF de la facture
            $options = new DompdfOptions();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('defaultFont', 'Arial');

            $dompdf = new Dompdf($options);
            
            // Générer le HTML de la facture
            $html = $this->twig->render('emails/invoice.html.twig', [
                'order' => $order
            ]);
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            
            $pdfContent = $dompdf->output();

            // Créer l'email avec la facture en pièce jointe
            $email = (new TemplatedEmail())
                ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
                ->to($to)
                ->subject('Confirmation de votre commande #' . $order->getId() . ' - Insatianet')
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context([
                    'order' => $order
                ])
                ->attach($pdfContent, sprintf('facture-%s.pdf', $order->getId()), 'application/pdf');

            $this->send($email);
            
            $this->logger->info('Email de confirmation de commande envoyé avec succès', [
                'order_id' => $order->getId(),
                'to' => $to
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation de commande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function sendOrderStatusUpdate(string $to, array $orderData): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($to)
            ->subject('Mise à jour de votre commande #' . $orderData['order']->getId() . ' - Insatianet')
            ->htmlTemplate('emails/order_status_update.html.twig')
            ->context($orderData);

        $this->send($email);
    }

    public function sendPaymentMethodConfirmation(string $to, array $data): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($to)
            ->subject('Confirmation de votre nouvelle carte bancaire - Insatianet')
            ->htmlTemplate('emails/payment_method_confirmation.html.twig')
            ->context($data);

        $this->send($email);
    }

    public function sendPasswordReset(string $to, $resetToken): void
    {
        try {
            $this->logger->info('Préparation de l\'email de réinitialisation', [
                'to' => $to
            ]);

            $email = (new TemplatedEmail())
                ->from(new Address(self::SENDER_EMAIL, 'Insatianet - Réinitialisation de mot de passe'))
                ->to(new Address($to))
                ->subject('Réinitialisation de votre mot de passe - Insatianet')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context([
                    'resetToken' => $resetToken,
                ]);

            $this->logger->debug('Email construit avec les paramètres suivants', [
                'from' => $email->getFrom(),
                'to' => $email->getTo(),
                'subject' => $email->getSubject()
            ]);

            $this->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la préparation de l\'email de réinitialisation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function sendEmailVerification(string $to, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, 'Insatianet - Vérification de compte'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->send($email);
    }
} 