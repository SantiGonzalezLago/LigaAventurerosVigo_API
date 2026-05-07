<?php

namespace App\Controllers\V1;

use App\Models\SettingsModel;

class PublicController extends BaseApiController {
  protected SettingsModel $settingsModel;

  public function __construct() {
    $this->settingsModel = new SettingsModel();
  }

  /**
   * Endpoint: GET /v1/social-links
   *
   * Devuelve:
   * - 200: { message: "ok", social_links: { social_link_instagram?: string, social_link_whatsapp?: string, social_link_kofi?: string } }
   */
  public function socialLinks() {
    $instagram = trim((string) ($this->settingsModel->getSetting('social_link_instagram') ?? ''));
    $whatsapp = trim((string) ($this->settingsModel->getSetting('social_link_whatsapp') ?? ''));
    $kofi = trim((string) ($this->settingsModel->getSetting('social_link_kofi') ?? ''));

    $socialLinks = [];

    if ($instagram !== '') {
      $socialLinks['social_link_instagram'] = $instagram;
    }

    if ($whatsapp !== '') {
      $socialLinks['social_link_whatsapp'] = $whatsapp;
    }

    if ($kofi !== '') {
      $socialLinks['social_link_kofi'] = $kofi;
    }

    return $this->respond([
      'message' => 'ok',
      'social_links' => $socialLinks,
    ], 200);
  }
}