<?php

namespace Drupal\digital_serial_issue\Plugin\views\field;

use Drupal\search_api\Plugin\views\field\SearchApiStandard;
use Drupal\views\Attribute\ViewsField;

/**
 * Renders an issue's cover from the first-page image URI held in the index.
 *
 * The URI comes from the Solr result (first_page_image_uri), and the image
 * style URL is built from that string alone, so no page or file entity is
 * loaded. An image field formatter cannot do this: it renders a field that
 * belongs to an entity, and Search API Views loads that entity for each row.
 *
 * The 150x240 dimensions are the output of the browse_archives style
 * (image_scale_and_crop), passed so the <img> carries them without reading the
 * source image.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('issue_cover_image')]
class IssueCoverImage extends SearchApiStandard {

  /**
   * The image style the cover is rendered with.
   */
  const IMAGE_STYLE = 'browse_archives';

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item) {
    $uri = $item['value'] ?? NULL;
    if (!is_string($uri) || $uri === '') {
      return '';
    }
    // Return rendered markup, not a render array: Views casts an array result
    // to a plain string, which the field's output then escapes.
    $build = [
      '#theme' => 'image_style',
      '#style_name' => self::IMAGE_STYLE,
      '#uri' => $uri,
      '#width' => 150,
      '#height' => 240,
      // Decorative: the card's link text already names the issue.
      '#alt' => '',
      '#attributes' => ['loading' => 'lazy'],
      '#cache' => [
        'tags' => ['config:image.style.' . self::IMAGE_STYLE],
      ],
    ];
    return $this->getRenderer()->render($build);
  }

}
