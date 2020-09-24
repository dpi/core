<?php

namespace Drupal\Core\Entity\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a controller showing revision history for an entity.
 *
 * This controller leverages the revision controller trait, which is agnostic to
 * any entity type, by using \Drupal\Core\Entity\RevisionLogInterface.
 */
class VersionHistoryController extends ControllerBase {

  use RevisionControllerTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new VersionHistoryController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, DateFormatterInterface $dateFormatter, RendererInterface $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->dateFormatter = $dateFormatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Generates an overview table of revisions for an entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   *
   * @return array
   *   A render array.
   */
  public function versionHistory(RouteMatchInterface $routeMatch): array {
    $entityTypeId = $routeMatch->getRouteObject()->getOption('entity_type_id');
    $entity = $routeMatch->getParameter($entityTypeId);
    return $this->revisionOverview($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRevertRevisionLink(RevisionableInterface $revision): ?array {
    if (!$revision->hasLinkTemplate('revision-revert-form')) {
      return NULL;
    }

    $url = $revision->toUrl('revision-revert-form');
    // Merge in cacheability after
    // https://www.drupal.org/project/drupal/issues/2473873.
    if (!$url->access()) {
      return NULL;
    }

    return [
      'title' => $this->t('Revert'),
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDeleteRevisionLink(EntityInterface $revision): ?array {
    if (!$revision->hasLinkTemplate('revision-delete-form')) {
      return NULL;
    }

    $url = $revision->toUrl('revision-delete-form');
    // Merge in cacheability after
    // https://www.drupal.org/project/drupal/issues/2473873.
    if (!$url->access()) {
      return NULL;
    }

    return [
      'title' => $this->t('Delete'),
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRevisionDescription(RevisionableInterface $revision): array {
    $context = [];
    if ($revision instanceof RevisionLogInterface) {
      // Use revision link to link to revisions that are not active.
      ['type' => $dateFormatType, 'format' => $dateFormatFormat] = $this->getRevisionDescriptionDateFormat($revision);
      $linkText = $this->dateFormatter->format($revision->getRevisionCreationTime(), $dateFormatType, $dateFormatFormat);

      // @todo: Simplify this when https://www.drupal.org/node/2334319 lands.
      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];
      $context['username'] = $this->renderer->render($username);
    }
    else {
      $linkText = $revision->access('view label') ? $revision->label() : $this->t('- Restricted access -');
    }

    $revisionViewLink = $revision->toLink($linkText, 'revision');
    $context['revision'] = $revisionViewLink->getUrl()->access()
      ? $revisionViewLink->toString()
      : (string) $revisionViewLink->getText();
    $context['message'] = $revision instanceof RevisionLogInterface ? [
      '#markup' => $revision->getRevisionLogMessage(),
      '#allowed_tags' => Xss::getHtmlTagList(),
    ] : '';

    return [
      'data' => [
        '#type' => 'inline_template',
        '#template' => isset($context['username'])
        ? '{% trans %} {{ revision }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}'
        : '{% trans %} {{ revision }} {% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
        '#context' => $context,
      ],
    ];
  }

  /**
   * Date format to use for revision description dates.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The revision in context.
   *
   * @return array
   *   An array with keys 'type' and optionally 'format' suitable for passing
   *   to date formatter service.
   */
  protected function getRevisionDescriptionDateFormat(RevisionableInterface $revision): array {
    return [
      'type' => 'short',
      'format' => '',
    ];
  }

}
