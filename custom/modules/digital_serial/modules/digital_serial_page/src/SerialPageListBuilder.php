<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Serial page entities.
 *
 * @ingroup digital_serial_page
 */
class SerialPageListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = 'No pages have been added to this issue yet.';
    // Add project modules.
    $build['add_pages_button'] = [
      '#attributes' => [
        'class' => ['btn btn-info btn-add'],
      ],
      '#title' => t('Add New Page'),
      '#type' => 'link',
      '#url' => Url::fromRoute(
        'entity.digital_serial_issue.add_page',
        [
          'digital_serial_issue' => \Drupal::routeMatch()->getParameters()->get('digital_serial_issue'),
        ]
      ),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['page_no'] = $this->t('Page No');
    /* $header['page_image'] = $this->t('Preview'); */
    $header['page_image2'] = $this->t('Page Preview');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_page\SerialPage */

    $issue_eid = \Drupal::routeMatch()->getParameters()->get('digital_serial_issue');

    // Add module to entity reference.
    $issue = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_issue')
      ->load($issue_eid);

    if ($issue->hasPage($entity)) {
      $row['page_no'] = $entity->getPageNo();
      /* $image = $entity->getStyledImage('thumbnail'); */
      /* $row['page_image'] = render($image); */
      $linked_image = $entity->getLinkedStyledImage('thumbnail');
      $row['page_image2'] = $linked_image->toString();

      return $row + parent::buildRow($entity);
    }
    return FALSE;
  }

}
