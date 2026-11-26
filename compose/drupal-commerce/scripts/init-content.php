<?php
/**
 * Catalogue de formations GroupeC avec Drupal Commerce.
 * - crée chaque formation comme produit Commerce (avec prix)
 * - crée la page /formations qui liste les formations avec prix + "Ajouter au panier"
 * - crée un moyen de paiement simulé (aucun paiement réel)
 * - donne aux visiteurs le droit de voir, mettre au panier et commander
 *
 * Utilise le magasin par défaut s'il existe, sinon en crée un (GroupeC, CAD).
 * Idempotent : peut être relancé sans créer de doublons.
 */

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\views\Entity\View;

#############################################
# 0. Magasin par défaut
#############################################

$storeStorage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$store = $storeStorage->loadDefault();
if (!$store) {
    // Pas de magasin : on en crée un de démonstration (devise CAD).
    \Drupal::service('commerce_price.currency_importer')->import('CAD');
    $store = Store::create([
        'type' => 'online',
        'uid' => 1,
        'name' => 'GroupeC',
        'mail' => 'admin@groupec.local',
        'default_currency' => 'CAD',
        'timezone' => 'America/Toronto',
        'address' => [
            'country_code' => 'CA',
            'administrative_area' => 'ON',
            'locality' => 'Toronto',
            'postal_code' => 'M5V 2T6',
            'address_line1' => '1 rue de la Démo',
            'organization' => 'GroupeC',
        ],
        'billing_countries' => ['CA'],
    ]);
    $store->setDefault(TRUE);
    $store->save();
    echo "Magasin de démonstration créé\n";
}
$devise = $store->getDefaultCurrencyCode();
echo "Magasin : " . $store->label() . " ($devise)\n";

#############################################
# 1. Formations = produits Commerce
#############################################

// SKU => [titre, prix]. Prix de démonstration : ajustez-les librement.
$formations = [
    'CYBER465'  => ['CYBER465', '600'],
    'DOCKER101' => ['Docker Essentials', '450'],
    'LINUX201'  => ['Administration Linux', '500'],
    'DRUPAL301' => ['Drupal 11 Avancé', '650'],
    'K8S101'    => ['Kubernetes Fundamentals', '700'],
    'N8N101'    => ['Automatisation avec n8n', '400'],
    'PGSQL201'  => ['PostgreSQL Administration', '550'],
    'GIT101'    => ['Git et GitLab', '350'],
    'SECLNX301' => ['Sécurité Linux', '600'],
    'ANSIBLE101'=> ['Ansible Débutant', '450'],
    'CLOUD101'  => ['Cloud Init et Multipass', '300'],
];

$varStorage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');

foreach ($formations as $sku => [$titre, $prix]) {
    $prixObj = new Price($prix, $devise);

    $variation = $varStorage->loadBySku($sku);
    if ($variation) {
        // Mettre à jour le prix si besoin.
        if (!$variation->getPrice() || !$variation->getPrice()->equals($prixObj)) {
            $variation->setPrice($prixObj);
            $variation->save();
            echo "Prix mis à jour : $titre -> $prix $devise\n";
        }
        continue;
    }

    $variation = ProductVariation::create([
        'type' => 'default',
        'sku' => $sku,
        'title' => $titre,
        'price' => $prixObj,
        'status' => 1,
    ]);
    $variation->save();

    Product::create([
        'type' => 'default',
        'title' => $titre,
        'uid' => 1,
        'stores' => [$store->id()],
        'variations' => [$variation],
        'body' => [
            'value' => '<p>Formation : ' . $titre . '</p>',
            'format' => 'basic_html',
        ],
        'status' => 1,
    ])->save();

    echo "Formation créée : $titre ($prix $devise)\n";
}

// Afficher le prix sur la fiche produit (injecté depuis la variation).
\Drupal::service('entity_display.repository')
    ->getViewDisplay('commerce_product_variation', 'default')
    ->setComponent('price', ['label' => 'inline', 'type' => 'commerce_price_default'])
    ->save();

// Le chemin /formations est fourni par groupec_n8n.routing.yml.

#############################################
# 3. Paiement simulé
#############################################

if (!PaymentGateway::load('paiement_simule')) {
    PaymentGateway::create([
        'id' => 'paiement_simule',
        'label' => 'Paiement simulé',
        'plugin' => 'manual',
        'status' => TRUE,
        'weight' => 0,
        'configuration' => [
            'display_label' => 'Paiement simulé (aucun débit réel)',
            'mode' => 'n/a',
            'collect_billing_information' => FALSE,
            'instructions' => [
                'value' => 'Ceci est une simulation : aucun paiement réel ne sera effectué.',
                'format' => 'plain_text',
            ],
        ],
    ])->save();
    echo "Moyen de paiement simulé créé\n";
}

#############################################
# 4. Droits des visiteurs
#############################################

$droits = ['view commerce_product', 'access checkout'];
foreach (['anonymous', 'authenticated'] as $role) {
    user_role_grant_permissions($role, $droits);
}
echo "Droits accordés : " . implode(', ', $droits) . "\n";
      #############################################
# 5. Page d'accueil = catalogue
#############################################

\Drupal::configFactory()
    ->getEditable('system.site')
    ->set('page.front', '/formations')
    ->save();
echo "Page d'accueil : /formations\n";

echo "Catalogue Commerce prêt : /formations\n";
