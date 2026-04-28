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
   * - 200: { message: "ok", social_links: { instagram?: string, whatsapp?: string, kofi?: string } }
   */
  public function socialLinks() {
    $instagram = trim((string) ($this->settingsModel->getSetting('social_link_instagram') ?? ''));
    $whatsapp = trim((string) ($this->settingsModel->getSetting('social_link_whatsapp') ?? ''));
    $kofi = trim((string) ($this->settingsModel->getSetting('social_link_kofi') ?? ''));

    $socialLinks = [];

    if ($instagram !== '') {
      $socialLinks['instagram'] = $instagram;
    }

    if ($whatsapp !== '') {
      $socialLinks['whatsapp'] = $whatsapp;
    }

    if ($kofi !== '') {
      $socialLinks['kofi'] = $kofi;
    }

    return $this->respond([
      'message' => 'ok',
      'social_links' => $socialLinks,
    ], 200);
  }
}