<?php

namespace Drupal\digital_serial_title\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Digital Serial Title entity.
 *
 * @ingroup digital_serial_title
 *
 * @ContentEntityType(
 *   id = "digital_serial_title",
 *   label = @Translation("Digital Serial Title"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_title\SerialTitleListBuilder",
 *     "views_data" = "Drupal\digital_serial_title\Entity\SerialTitleViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "add" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "edit" = "Drupal\digital_serial_title\Form\SerialTitleForm",
 *       "delete" = "Drupal\digital_serial_title\Form\SerialTitleDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_title\SerialTitleAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_title\SerialTitleHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "digital_serial_title",
 *   admin_permission = "administer digital serial title entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   label_callback = "digital_serial_title_format_label",
 *   links = {
 *     "canonical" = "/serials/{digital_serial_title}",
 *     "add-form" = "/serials/add",
 *     "edit-form" = "/serials/{digital_serial_title}/edit",
 *     "delete-form" = "/serials/{digital_serial_title}/delete",
 *     "collection" = "/serials",
 *   },
 *   field_ui_base_route = "digital_serial_title.settings"
 * )
 */
class SerialTitle extends ContentEntityBase implements SerialTitleInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentPublication() {
    return $this->get('parent_title')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedDisplayTitle() {
    return $this->get('parent_title')->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Digital Serial Title entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['parent_title'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent Title'))
      ->setSettings(
        [
          'target_type' => 'node',
          'handler' => 'default',
          'handler_settings' => ['target_bundles' => ['publication' => 'publication']],
        ]
      );

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Digital Serial Title is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->deleteChildPages();
    $this->deleteHoldingRecords();
    parent::delete();
  }

  /**
   * Delete the child issues that belong to this title.
   */
  private function deleteChildPages() {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
      ->condition('parent_title', $this->id());
    $issue_ids = $query->execute();

    foreach ($issue_ids as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      $issue->delete();
    }
  }

  /**
   * Delete the digital holdings records that belong to this title.
   */
  private function deleteHoldingRecords() {
    $query = \Drupal::entityQuery('serial_holding')
      ->accessCheck(TRUE)
      ->condition('holding_digital_title', $this->id());
    $holding_ids = $query->execute();

    foreach ($holding_ids as $holding_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('serial_holding');
      $holding = $storage->load($holding_id);
      $holding->delete();
    }
  }

  /**
   * Get a holdings statement for this digital title.
   */
  public function getHoldingsStatement() {
    $range = $this->getHoldingDateRange();
    if ($range['count'] > 1) {
      $holdings_statement = $this->t('Issues between ') .
        $range['start']->format('Y-m-d') .
        ' - ' .
        $range['end']->format('Y-m-d');
      return $holdings_statement;
    }
    elseif ($range['count'] == 1) {
      return $range['start']->format('Y-m-d');
    }
    else {
      return "No holdings found";
    }
  }

  /**
   * Get a holdings dates for all issues.
   *
   * This loads every issue of the title. Use getHoldingDateRange() when only
   * the earliest and latest dates are needed.
   */
  public function getHoldingDates() {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
      ->condition('parent_title', $this->id());
    $issue_ids = $query->execute();

    $holdings_dates = [];
    foreach ($issue_ids as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      if (!empty($issue->get('issue_date')->date)) {
        $holdings_dates[] = $issue->get('issue_date')->date;
      }
    }
    sort($holdings_dates);

    return $holdings_dates;
  }

  /**
   * Gets the earliest and latest issue dates of this title.
   *
   * Runs one aggregate query instead of loading the title's issues, so its
   * cost does not grow with the number of issues.
   *
   * @return array
   *   An array with the keys:
   *   - count: The number of issues that have a date.
   *   - start: The earliest issue date as a DrupalDateTime, NULL if count is 0.
   *   - end: The latest issue date as a DrupalDateTime, NULL if count is 0.
   */
  public function getHoldingDateRange() {
    // Issue dates are stored as Y-m-d strings, which sort chronologically.
    $result = \Drupal::entityQueryAggregate('digital_serial_issue')
      ->accessCheck(TRUE)
      ->condition('parent_title', $this->id())
      ->condition('issue_date', '', '<>')
      ->aggregate('id', 'COUNT')
      ->aggregate('issue_date', 'MIN')
      ->aggregate('issue_date', 'MAX')
      ->execute();
    $row = reset($result);

    $count = empty($row['id_count']) ? 0 : (int) $row['id_count'];
    return [
      'count' => $count,
      'start' => $count ? new DrupalDateTime($row['issue_date_min']) : NULL,
      'end' => $count ? new DrupalDateTime($row['issue_date_max']) : NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateDigitalHoldingRecord() {
    $query = \Drupal::entityQuery('serial_holding')
      ->accessCheck(TRUE)
      ->condition('holding_digital_title', $this->id());
    $holding_ids = $query->execute();

    if (!empty($holding_ids)) {
      $range = $this->getHoldingDateRange();
      $storage = \Drupal::entityTypeManager()->getStorage('serial_holding');
      foreach ($holding_ids as $holding_id) {
        $holding = $storage->load($holding_id);
        if ($range['count'] == 0) {
          continue;
        }
        // Most issue saves leave the title's date range as it was. Skip the
        // holding save then, as it only repeats the stored values.
        $start = $range['start']->format('Y-m-d');
        $end = $range['end']->format('Y-m-d');
        if ($holding->get('holding_start_date')->value === $start && $holding->get('holding_end_date')->value === $end) {
          continue;
        }
        $holding->setHoldingStartDate($range['start']);
        $holding->setHoldingEndDate($range['end']);
        $holding->save();
      }
    }

    // The title page and the parent publication page show issue counts and
    // holding dates that no cache tag on those pages tracks. Invalidate both
    // rather than flushing every cache.
    $tags = $this->getCacheTagsToInvalidate();
    $publication = $this->getParentPublication();
    if (!empty($publication)) {
      $tags = Cache::mergeTags($tags, $publication->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);

    // A publication is indexed as held online only while this title has a
    // published issue. Reindex it when that may have changed: the title has
    // just gained its first published issue or lost its last one.
    if (!empty($publication)) {
      $published_issues = \Drupal::entityQuery('digital_serial_issue')
        ->accessCheck(FALSE)
        ->condition('status', 1)
        ->condition('parent_title', $this->id())
        ->range(0, 2)
        ->execute();
      if (count($published_issues) < 2) {
        _serials_reindex_publications([$publication->id()]);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getStorageUri() {
    $default_file_scheme = \Drupal::config('system.file')->get('default_scheme');
    $title_id = $this->id();
    return "$default_file_scheme://serials/pages/$title_id";
  }

  /**
   * {@inheritDoc}
   */
  public function getStoragePath() {
    $title_id = $this->id();
    return DRUPAL_ROOT . "/sites/default/files/serials/pages/$title_id";
  }

  /**
   * {@inheritDoc}
   */
  public function createStoragePath() {
    $title_absolute_path = $this->getStoragePath();
    if (!file_exists($title_absolute_path)) {
      mkdir($title_absolute_path, 0755, TRUE);
    }
    return $title_absolute_path;
  }

  /**
   * {@inheritDoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->createStoragePath();
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritDoc}
   */
  public function getIssues($limit = 0) {
    $issues = [];
    foreach ($this->getIssueIds($limit) as $issue_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_id);
      $issues[] = $issue;
    }
    return $issues;
  }

  /**
   * {@inheritDoc}
   */
  public function getIssueIds($limit = 0) {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
      ->condition('parent_title', $this->id());
    if ($limit > 0) {
      $query->range(0, $limit);
    }
    return $query->execute();
  }

}
