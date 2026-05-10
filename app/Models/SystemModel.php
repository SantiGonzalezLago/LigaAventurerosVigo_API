<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemModel extends Model {

  protected $table = 'system';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'name',
    'slug',
    'icon',
    'pc_limit',
    'active',
  ];

  public function getAll($active = false): array {
    $builder = $this->db->table($this->table)->orderBy('id', 'asc');

    if ($active) {
      $builder->where('active', 1);
    }

    return $builder->get()->getResult();
  }

  public function getBySlug(string $slug): ?object {
    $result = $this->db->table($this->table)
      ->where('slug', $slug)
      ->limit(1)
      ->get()
      ->getRow();

    return $result instanceof \stdClass ? $result : null;
  }

  public function addSystem(string $name, string $slug, ?string $icon, int $pcLimit = 0, bool $active = true): int {
    $this->db->table($this->table)->insert([
      'name'     => $name,
      'slug'     => $slug,
      'icon'     => $icon,
      'pc_limit' => $pcLimit,
      'active'   => $active ? 1 : 0,
    ]);

    return (int) $this->db->insertID();
  }

  public function updateSystem(int $id, array $data): bool {
    $exists = $this->db->table($this->table)
      ->where('id', $id)
      ->countAllResults() > 0;

    if (!$exists) {
      return false;
    }

    return (bool) $this->db->table($this->table)
      ->where('id', $id)
      ->set($data)
      ->update();
  }

  public function slugExists(string $slug, ?int $excludeId = null): bool {
    $builder = $this->db->table($this->table)->where('slug', $slug);

    if ($excludeId !== null) {
      $builder->where('id !=', $excludeId);
    }

    return $builder->countAllResults() > 0;
  }

  public function existsById(int $id): bool {
    return $this->db->table($this->table)
      ->where('id', $id)
      ->countAllResults() > 0;
  }

}
