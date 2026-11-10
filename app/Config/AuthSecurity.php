<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AuthSecurity extends BaseConfig
{
    public int $failureWindow = 900;
    public int $cooldown = 300;
    public int $accountMaxFailures = 5;
    public int $ipMaxFailures = 50;
    public int $retention = 86400;
}
