<?php
    $config['plugins'] = [];
    $config['log_driver'] = 'stdout';
    $config['zipdownload_selection'] = true;
    $config['des_key'] = 'Wd6zn6c9A+DVnvks6GHxl2d3';
    $config['enable_spellcheck'] = true;
    $config['spellcheck_engine'] = 'pspell';

    // Configuração do servidor IMAP
    $config['default_host'] = 'imap_server';  // Substitua 'imap_server' pelo nome do contêiner ou IP
    $config['default_port'] = 143;  // Se for IMAPS, use 993
    
    include(__DIR__ . '/config.docker.inc.php');
    
