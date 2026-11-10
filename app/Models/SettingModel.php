<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['setting_key', 'setting_value'];
    protected $useTimestamps = true;
}