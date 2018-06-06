<?php

namespace Drupal\adminic_toolbar;

use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Menu\MenuTreeParameters;

class ToolbarConfigFromMenu {

  /**
   * Menu link tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  private $menuLinkTree;
  private $primarySections = [];
  private $primarySectionsTabs = [];
  private $secondarySections = [];
  private $secondarySectionsLinks = [];

  /**
   * ToolbarConfigFromMenu constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTree $menuLinkTree
   *   Menu link tree.
   */
  public function __construct(MenuLinkTree $menuLinkTree) {
    $this->menuLinkTree = $menuLinkTree;
  }

  public function getConfig($menuName = 'admin') {
    $configs = [];
    $menuTree = $this->getMenuTree($menuName);
    $this->parseMenuTree($menuTree);
    $configs['primary_sections'] = $this->getPrimarySections();
    $configs['primary_sections_tabs'] = $this->getPrimarySectionsTabs();
    $configs['secondary_sections'] = $this->getSecondarySections();
    $configs['secondary_sections_links'] = $this->getSecondarySectionsLinks();

    return $configs;
  }

  protected function parseMenuTree($menuTree) {
    // Primary Sections.
    // Get first link of menu tree.
    $root = reset($menuTree);
    /** @var \Drupal\Core\Menu\MenuLinkDefault $primarySection */
    $primarySection = $root->link;

    $primarySectionId = $primarySection->getMenuName();
    $primarySectionTitle = $primarySection->getTitle();

    $this->primarySections[$primarySectionId] = [
      'id' => $primarySectionId,
      'title' => $primarySectionTitle,
    ];

    // Primary sections tabs.
    $primarySectionTabs = $root->subtree;

    foreach ($primarySectionTabs as $primarySectionTab) {
      /** @var \Drupal\Core\Menu\MenuLinkDefault $tab */
      $tab = $primarySectionTab->link;

      $tabId = $tab->getRouteName();
      $tabPrimarySectionId = $primarySection->getMenuName();
      $tabRouteName = $tab->getRouteName();
      $tabRouteParameters = $tab->getRouteParameters();

      $this->primarySectionsTabs[$tabId] = [
        'id' => $tabId,
        'primary_section_id' => $tabPrimarySectionId,
        'route_name' => $tabRouteName,
        'route_parameters' => $tabRouteParameters,
      ];

      // Secondary sections.
      $secondarySections = $primarySectionTab->subtree;
      $defaultSubsectionId = $tabId . '.default';

      // Create default secondary section.
      $this->secondarySections[$defaultSubsectionId] = [
        'id' => $defaultSubsectionId,
        'tab_id' => $tabId,
      ];

      foreach ($secondarySections as $secondarySection) {
        /** @var \Drupal\Core\Menu\MenuLinkDefault $subsection */
        $subsection = $secondarySection->link;
        $subsectionHasChildren = $secondarySection->hasChildren;
        // If link have subtree create secondary section.
        if ($subsectionHasChildren == TRUE) {
          $subsectionId = $subsection->getRouteName();
          $subsectionTitle = $subsection->getTitle();
          $this->secondarySections[$subsectionId] = [
            'id' => $subsectionId,
            'tab_id' => $tabId,
            'title' => $subsectionTitle,
          ];
        }

        // Secondary section links.
        // Add tab link at the top of default section.
        $this->secondarySectionsLinks[] = [
          'secondary_section_id' => $defaultSubsectionId,
          'route_name' => $tabRouteName,
          'route_parameters' => $tabRouteParameters,
          'weight' => -99999,
        ];

        if ($subsectionHasChildren == FALSE) {
          $this->secondarySectionsLinks[] = [
            'secondary_section_id' => $defaultSubsectionId,
            'route_name' => $subsection->getRouteName(),
            'route_parameters' => $subsection->getRouteParameters(),
          ];
        }

        $secondarySectionsLinks = $secondarySection->subtree;

        foreach ($secondarySectionsLinks as $secondarySectionsLink) {
          /** @var \Drupal\Core\Menu\MenuLinkDefault $link */
          $link = $secondarySectionsLink->link;

          $linkRouteName = $link->getRouteName();
          $linkRouteParameters = $link->getRouteParameters();

          $this->secondarySectionsLinks[] = [
            'secondary_section_id' => $subsectionId,
            'route_name' => $linkRouteName,
            'route_parameters' => $linkRouteParameters,
          ];
        }
      }
    }
  }

  protected function getMenuTree($menuName) {
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(4);
    $tree = $this->menuLinkTree->load($menuName, $parameters);

    $manipulators = array(
      // Only show links that are accessible for the current user.
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      // Use the default sorting of menu links.
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    return $tree;
  }

  protected function getPrimarySections() {
    return $this->primarySections;
  }

  protected function getPrimarySectionsTabs() {
    return $this->primarySectionsTabs;
  }

  protected function getSecondarySections() {
    return $this->secondarySections;
  }

  protected function getSecondarySectionsLinks() {
    return $this->secondarySectionsLinks;
  }

}
