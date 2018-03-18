<?php

namespace Drupal\adminic_toolbar;

/**
 * @file
 * ToolbarSecondayrSectionsManager.php.
 */

use Drupal\Core\Extension\ModuleHandler;
use Exception;

/**
 * Class ToolbarSecondayrSectionsManager.
 *
 * @package Drupal\adminic_toolbar
 */
class ToolbarSecondarySectionsManager {

  const PRIMARY_SECTIONS = 'primary_sections';
  const SECONDARY_SECTIONS = 'secondary_sections';
  const SECTION_ID = 'id';
  const SECTION_TAB_ID = 'tab_id';
  const SECTION_PLUGIN_ID = 'plugin_id';
  const SECTION_TITLE = 'title';
  const SECTION_PRESET = 'preset';
  const SECTION_WEIGHT = 'weight';
  const SECTION_DISABLED = 'disabled';

  /**
   * Discovery manager.
   *
   * @var \Drupal\adminic_toolbar\ToolbarConfigDiscovery
   */
  private $toolbarConfigDiscovery;

  /**
   * Route manager.
   *
   * @var \Drupal\adminic_toolbar\ToolbarRouteManager
   */
  private $toolbarRouteManager;

  /**
   * Links manager.
   *
   * @var \Drupal\adminic_toolbar\ToolbarSecondarySectionLinksManager
   */
  private $toolbarLinkManager;

  /**
   * Tabs manager.
   *
   * @var \Drupal\adminic_toolbar\ToolbarPrimarySectionTabsManager
   */
  private $toolbarTabManager;

  /**
   * Primary Sections.
   *
   * @var array
   */
  private $primarySections = [];

  /**
   * Secondary Sections.
   *
   * @var array
   */
  private $secondarySections = [];

  /**
   * Active sections.
   *
   * @var array
   */
  private $activeSections = [];

  /**
   * Class that manages modules in a Drupal installation.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  private $moduleHandler;

  /**
   * Toolbar widget plugin manager.
   *
   * @var \Drupal\adminic_toolbar\ToolbarPluginManager
   */
  private $toolbarPluginManager;

  /**
   * SectionsManager constructor.
   *
   * @param \Drupal\adminic_toolbar\ToolbarConfigDiscovery $toolbarConfigDiscovery
   *   Toolbar config discovery.
   * @param \Drupal\adminic_toolbar\ToolbarRouteManager $toolbarRouteManager
   *   Toolbar route manager.
   * @param \Drupal\adminic_toolbar\ToolbarSecondarySectionLinksManager $toolbarLinksManager
   *   Toolbar links manager.
   * @param \Drupal\adminic_toolbar\ToolbarPrimarySectionTabsManager $toolbarTabsManager
   *   Toolbar tabs manager.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   Class that manages modules in a Drupal installation.
   * @param \Drupal\adminic_toolbar\ToolbarPluginManager $toolbarPluginManager
   *   Toolbar widget plugin manager.
   */
  public function __construct(
    ToolbarConfigDiscovery $toolbarConfigDiscovery,
    ToolbarRouteManager $toolbarRouteManager,
    ToolbarSecondarySectionLinksManager $toolbarLinksManager,
    ToolbarPrimarySectionTabsManager $toolbarTabsManager,
    ModuleHandler $moduleHandler,
    ToolbarPluginManager $toolbarPluginManager) {
    $this->toolbarConfigDiscovery = $toolbarConfigDiscovery;
    $this->toolbarLinkManager = $toolbarLinksManager;
    $this->toolbarTabManager = $toolbarTabsManager;
    $this->toolbarRouteManager = $toolbarRouteManager;
    $this->moduleHandler = $moduleHandler;
    $this->toolbarPluginManager = $toolbarPluginManager;
  }

  /**
   * Get all defined sections from all config files.
   *
   * @throws \Exception
   */
  protected function discoverySecondarySections() {

    $config = $this->toolbarConfigDiscovery->getConfig();

    $weight = 0;
    $configSections = [];
    foreach ($config as $configFile) {
      if (isset($configFile[self::SECONDARY_SECTIONS])) {
        foreach ($configFile[self::SECONDARY_SECTIONS] as $section) {
          // If weight is empty set computed value.
          $section[self::SECTION_WEIGHT] = isset($section[self::SECTION_WEIGHT]) ? $section[self::SECTION_WEIGHT] : $weight++;
          // If set is empty set default set.
          $section[self::SECTION_PRESET] = isset($section[self::SECTION_PRESET]) ? $section[self::SECTION_PRESET] : 'default';
          // TODO: get key from method.
          $key = $section[self::SECTION_ID];
          $configSections[$key] = $section;
        }
      }
    }
    // Sort tabs by weight.
    uasort($configSections, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    // Call hook alters.
    $this->moduleHandler->alter('toolbar_config_primary_sections', $configSections);

    // Add tabs.
    $this->createSecondarySectionsCollection($configSections);
  }

  /**
   * Add sections.
   *
   * @param array $configSections
   *   Array of sections.
   */
  protected function createSecondarySectionsCollection(array $configSections) {
    $activeSecondarySection = NULL;
    $activeRoutes = $this->toolbarRouteManager->getActiveLinks();
    if (!empty($activeRoutes)) {
      $firstActiveRoute = reset($activeRoutes);
      $activeSecondarySection = $firstActiveRoute['secondary_section_id'];
    }

    foreach ($configSections as $section) {
      if ($section[self::SECTION_PRESET] == $this->toolbarConfigDiscovery->getActiveSet()) {
        $this->validateSecondarySectionInput($section);

        $id = $section[self::SECTION_ID];
        $title = isset($section[self::SECTION_TITLE]) ? $section[self::SECTION_TITLE] : '';
        $tab_id = isset($section[self::SECTION_TAB_ID]) ? $section[self::SECTION_TAB_ID] : '';
        $disabled = isset($section[self::SECTION_DISABLED]) ? $section[self::SECTION_DISABLED] : FALSE;
        $type = isset($section[self::SECTION_PLUGIN_ID]) ? $section[self::SECTION_PLUGIN_ID] : '';
        $newSection = new ToolbarPrimarySection($id, $title, $tab_id, $disabled, $type);
        $this->addSecondarySection($newSection);

        if ($activeSecondarySection != NULL && $id == $activeSecondarySection) {
          $this->toolbarRouteManager->setActiveSecondarySection($section);
        }
      }
    }
  }

  /**
   * Add section.
   *
   * @param \Drupal\adminic_toolbar\ToolbarPrimarySection $section
   *   Section.
   */
  public function addSecondarySection(ToolbarPrimarySection $section) {
    $key = $this->getSectionKey($section);
    $this->secondarySections[$key] = $section;
    // Remove section if exists but is disabled.
    if (isset($this->secondarySections[$key]) && $section->isDisabled()) {
      unset($this->secondarySections[$key]);
    }
  }

  /**
   * Get sections defined for primary toolbar.
   *
   * @return array
   *   Array of sections.
   *
   * @throws \Exception
   */
  public function getSecondarySections() {
    if (empty($this->secondarySections)) {
      $this->discoverySecondarySections();
    }

    return $this->secondarySections;
  }

  /**
   * Get secondary sections wrappers.
   *
   * @return array
   *   Return array of secondary section wrappers.
   *
   * @throws \Exception
   */
  public function getSecondarySectionWrappers() {
    $tabs = $this->toolbarTabManager->getTabs();
    $secondaryWrappers = [];
    /** @var \Drupal\adminic_toolbar\ToolbarPrimarySectionTab $tab */
    foreach ($tabs as $tab) {
      $sections = $this->getSecondarySectionsByTab($tab);

      $secondaryWrappers[$tab->getId()] = [
        'title' => $tab->getTitle(),
        'route' => $tab->getUrl(),
        'sections' => $sections,
      ];
    }

    return $secondaryWrappers;
  }

  /**
   * Get secondary sections by tab.
   *
   * @param \Drupal\adminic_toolbar\ToolbarPrimarySectionTab $tab
   *   Tab.
   *
   * @return array
   *   Return array of secondary sections for specified tab.
   *
   * @throws \Exception
   */
  protected function getSecondarySectionsByTab(ToolbarPrimarySectionTab $tab) {
    $sections = $this->getSecondarySections();

    /** @var \Drupal\adminic_toolbar\ToolbarPrimarySectionTab $tab */
    $secondarySections = array_filter(
      $sections, function ($section) use ($tab) {
        /** @var \Drupal\adminic_toolbar\ToolbarPrimarySection $section */
        $sectionTab = $section->getTab();
        return !empty($sectionTab) && $sectionTab == $tab->getId();
      }
    );

    if (!empty($secondarySections)) {
      $renderedSections = $this->getRenderedSections($secondarySections);
      return $renderedSections;
    }

    return NULL;
  }

  /**
   * Get renderable array for secondary section.
   *
   * @param \Drupal\adminic_toolbar\ToolbarPrimarySection $section
   *   Section.
   *
   * @return array|null
   *   Return renderable array or null.
   *
   * @throws \Exception
   */
  protected function getSecondarySection(ToolbarPrimarySection $section) {
    if ($section->hasType()) {
      $type = $section->getType();
      $widget = $this->toolbarPluginManager->createInstance($type);
      return $widget->getRenderArray();
    }

    $links = $this->toolbarLinkManager->getLinks();
    $sectionId = $section->getId();

    $sectionValidLinks = array_filter(
      $links, function ($link) use ($sectionId) {
        /** @var \Drupal\adminic_toolbar\ToolbarSecondarySectionLink $link */
        return $link->getToolbarPlugin() == $sectionId;
      }
    );

    if (empty($sectionValidLinks)) {
      return NULL;
    }

    $sectionLinks = [];
    /** @var \Drupal\adminic_toolbar\ToolbarSecondarySectionLink $link */
    foreach ($sectionValidLinks as $link) {
      $sectionLinks[] = $link->getRenderArray();
    }

    if ($sectionLinks) {
      $section->setLinks($sectionLinks);
      return $section->getRenderArray();
    }

    return NULL;
  }

  /**
   * Get section key.
   *
   * @param \Drupal\adminic_toolbar\ToolbarPrimarySection $section
   *   Section.
   *
   * @return string
   *   Return section key.
   */
  protected function getSectionKey(ToolbarPrimarySection $section) {
    return $section->getId();
  }

  /**
   * Validate section required parameters.
   *
   * @param array $section
   *   Section array.
   */
  protected function validateSecondarySectionInput(array $section) {
    try {
      $obj = json_encode($section);
      if (empty($section[self::SECTION_ID])) {
        throw new Exception('Section ID parameter missing ' . $obj);
      };
    }
    catch (Exception $e) {
      print $e->getMessage();
    }
  }

  /**
   * Get rendered section.
   *
   * @param array $secondarySections
   *   Array keyed by what?
   *
   * @todo Add better explanation.
   *
   * @return array
   *   Array of renderable arrays.
   *
   * @throws \Exception
   */
  protected function getRenderedSections(array $secondarySections) {
    $renderedSections = [];
    foreach ($secondarySections as $key => $secondarySection) {
      $section = $this->getSecondarySection($secondarySection);
      if ($section) {
        $renderedSections[$key] = $section;
      }
    }
    return $renderedSections;
  }

}
