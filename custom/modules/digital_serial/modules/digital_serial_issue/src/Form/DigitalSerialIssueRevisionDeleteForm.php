<?php

namespace Drupal\digital_serial_issue\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Digital serial issue revision.
 *
 * @ingroup digital_serial_issue
 */
class DigitalSerialIssueRevisionDeleteForm extends ConfirmFormBase {


  /**
   * The Digital serial issue revision.
   *
   * @var \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   */
  protected $revision;

  /**
   * The Digital serial issue storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $DigitalSerialIssueStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new DigitalSerialIssueRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityStorageInterface $entity_storage, Connection $connection) {
    $this->DigitalSerialIssueStorage = $entity_storage;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $entity_manager->getStorage('digital_serial_issue'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'digital_serial_issue_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => format_date($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.digital_serial_issue.version_history', ['digital_serial_issue' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_issue_revision = NULL) {
    $this->revision = $this->DigitalSerialIssueStorage->loadRevision($digital_serial_issue_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->DigitalSerialIssueStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Digital serial issue: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    drupal_set_message(t('Revision from %revision-date of Digital serial issue %title has been deleted.', ['%revision-date' => format_date($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.digital_serial_issue.canonical',
       ['digital_serial_issue' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {digital_serial_issue_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.digital_serial_issue.version_history',
         ['digital_serial_issue' => $this->revision->id()]
      );
    }
  }

}
