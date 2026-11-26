<?php

namespace Drupal\groupec_n8n\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Site\Settings;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Envoie la commande à n8n :
 * - "commande_passee" : le client a validé la commande (fin du checkout,
 *   donc après le paiement simulé) ;
 * - "commande_payee"  : la commande est entièrement payée
 *   (ex. paiement marqué "reçu" par un admin dans Commerce > Commandes).
 */
class OrderToN8nSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
            'commerce_order.order.paid' => 'onOrderPaid',
    ];
  }

  public function onOrderPlaced(WorkflowTransitionEvent $event): void {
    $this->send($event->getEntity(), 'commande_passee');
  }

  public function onOrderPaid(OrderEvent $event): void {
    $this->send($event->getOrder(), 'commande_payee');
  }

  /**
   * Envoie la commande au webhook n8n. Une erreur n'empêche jamais la commande.
   */
  protected function send(OrderInterface $order, string $evenement): void {
    // URL du webhook : settings.php, sinon variable d'environnement, sinon défaut.
    $url = Settings::get('n8n_webhook_url')
      ?: (getenv('N8N_WEBHOOK_URL') ?: 'http://n8n:5678/webhook/commande-payee');
    $secretfile = getenv('N8N_WEBHOOK_TOKEN_FILE') ?: '/run/secrets/n8n_webhook_token';
    $token = is_readable($secretfile) ? trim(file_get_contents($secretfile)) : '';
    if (!$token) {
      $this->logger->error('Jeton interne n8n manquant, commande non transmise.');
      return;
    }

    $articles = [];
    foreach ($order->getItems() as $item) {
      $produit = $item->getPurchasedEntity();
      $articles[] = [
        'titre' => $item->getTitle(),
        'sku' => $produit ? $produit->getSku() : NULL,
        'quantite' => (float) $item->getQuantity(),
        'prix_unitaire' => $item->getUnitPrice() ? $item->getUnitPrice()->getNumber() : NULL,
        'total' => $item->getTotalPrice() ? $item->getTotalPrice()->getNumber() : NULL,
      ];
    }

    $total = $order->getTotalPrice();
    $paye = $order->getTotalPaid();
    $client = $order->getCustomer();

    $donnees = [
      'evenement' => $evenement,
      'commande_id' => (int) $order->id(),
      'numero' => $order->getOrderNumber(),
      'etat' => $order->getState()->getId(),
      'email' => $order->getEmail(),
      'client' => $client && !$client->isAnonymous() ? $client->getDisplayName() : 'Invité',
      'total' => $total ? $total->getNumber() : NULL,
      'total_paye' => $paye ? $paye->getNumber() : NULL,
      'devise' => $total ? $total->getCurrencyCode() : NULL,
      'paiement' => $order->hasField('payment_gateway')
                ? $order->get('payment_gateway')->target_id : null,
      'date' => date('c', $order->getPlacedTime() ?: time()),
      'articles' => $articles,
      'site' => \Drupal::request()->getSchemeAndHttpHost(),
    ];

    $headers = ['Content-Type' => 'application/json'];
    if ($token) {
      $headers['X-GroupeC-Token'] = $token;
    }

    try {
      $this->httpClient->request('POST', $url, [
        'json' => $donnees,
        'headers' => $headers,
        'timeout' => 5,
        'connect_timeout' => 3,
      ]);
      $this->logger->info('Commande @n envoyée à n8n (@e).', [
        '@n' => $donnees['numero'] ?? $donnees['commande_id'],
        '@e' => $evenement,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('Échec envoi n8n (@u) : @m', ['@u' => $url, '@m' => $e->getMessage()]);
    }
  }

}
