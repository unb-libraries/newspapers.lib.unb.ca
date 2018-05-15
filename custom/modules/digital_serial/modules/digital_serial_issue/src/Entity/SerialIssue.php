<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Serial issue entity.
 *
 * @ingroup digital_serial_issue
 *
 * @ContentEntityType(
 *   id = "serial_issue",
 *   label = @Translation("Serial issue"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\digital_serial_issue\SerialIssueListBuilder",
 *     "views_data" = "Drupal\digital_serial_issue\Entity\SerialIssueViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "add" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "edit" = "Drupal\digital_serial_issue\Form\SerialIssueForm",
 *       "delete" = "Drupal\digital_serial_issue\Form\SerialIssueDeleteForm",
 *     },
 *     "access" = "Drupal\digital_serial_issue\SerialIssueAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\digital_serial_issue\SerialIssueHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "serial_issue",
 *   admin_permission = "administer serial issue entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/serial_issue/{serial_issue}",
 *     "add-form" = "/admin/structure/serial_issue/add",
 *     "edit-form" = "/admin/structure/serial_issue/{serial_issue}/edit",
 *     "delete-form" = "/admin/structure/serial_issue/{serial_issue}/delete",
 *     "collection" = "/admin/structure/serial_issue",
 *   },
 *   field_ui_base_route = "serial_issue.settings"
 * )
 */
class SerialIssue extends ContentEntityBase implements SerialIssueInterface {

  use EntityChangedTrait;

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
  public function getIssueTitle() {
    return $this->get('issue_title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueTitle($issue_title) {
    $this->set('issue_title', $issue_title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueVol() {
    return $this->get('issue_vol')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueVol($issue_vol) {
    $this->set('issue_vol', $issue_vol);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueIssue() {
    return $this->get('issue_issue')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueIssue($issue_issue) {
    $this->set('issue_issue', $issue_issue);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueEdition() {
    return $this->get('issue_edition')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIssueEdition($issue_edition) {
    $this->set('issue_edition', $issue_edition);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['issue_title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Printed Title'))
      ->setDescription(t('Printed title.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -50,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -50,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_vol'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Volume'))
      ->setDescription(t('Volume number of the issue.'))
      ->setSettings([
        'max_length' => 16,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -45,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -45,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


    $fields['issue_issue'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Issue'))
      ->setDescription(t('Issue number of the issue.'))
      ->setSettings([
        'max_length' => 16,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -40,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -40,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issue_edition'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Edition'))
      ->setDescription(t('Edition of the issue.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Serial issue is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
