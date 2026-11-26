<?php
namespace Drupal\groupec_n8n\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

/** Catalogue public de formations Commerce (V5). */
final class CatalogueController extends ControllerBase {
  public function catalogue(): array {
    $storage = $this->entityTypeManager()->getStorage('commerce_product');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('status', 1)->execute();
    $items = [];
    foreach ($storage->loadMultiple($ids) as $product) {
      $variations = $product->getVariations();
      $v = reset($variations);
      $price = ($v && $v->getPrice())
        ? $v->getPrice()->getNumber() . ' ' . $v->getPrice()->getCurrencyCode()
        : '';
      $items[] = Link::fromTextAndUrl($product->label() . ' — ' . $price, $product->toUrl())->toRenderable();
    }
    return [
      'intro' => ['#markup' => '<p>Choisissez une formation et ajoutez-la au panier. Paiement fictif uniquement.</p>'],
      'formations' => ['#theme' => 'item_list', '#items' => $items],
      '#cache' => ['max-age' => 0],
    ];
  }
}
