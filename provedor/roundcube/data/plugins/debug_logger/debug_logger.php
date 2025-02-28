<?php

/**
 * Debug Logger
 *
 * Enhanced logging for debugging purposes.  It is not recommended
 * to be enabled on production systems without testing because of
 * the somewhat increased memory, cpu and disk i/o overhead.
 *
 * Debug Logger listens for existing rcube::console() calls and
 * introduces start and end tags as well as free form tagging
 * which can redirect messages to files.  The resulting log files
 * provide timing and tag quantity results.
 *
 * Enable the plugin in config.inc.php and add your desired
 * log types and files.
 *
 * @author Ziba Scott
 * @website https://roundcube.net
 *
 * Example:
 *
 *   // $config['debug_logger'][type of logging] = name of file in log_dir
 *   // The 'master' log includes timing information
 *   $config['debug_logger']['master'] = 'master';
 *   // If you want sql messages to also go into a separate file
 *   $config['debug_logger']['sql'] = 'sql';
 *
 * index.php (just after $RCMAIL->plugins->init()):
 *
 *   rcube::console("my test","start");
 *   rcube::console("my message");
 *   rcube::console("my sql calls","start");
 *   rcube::console("cp -r * /dev/null","shell exec");
 *   rcube::console("select * from example","sql");
 *   rcube::console("select * from example","sql");
 *   rcube::console("select * from example","sql");
 *   rcube::console("end");
 *   rcube::console("end");
 *
 * logs/master (after reloading the main page):
 *
 *   [17-Feb-2009 16:51:37 -0500] start: Task: mail.
 *   [17-Feb-2009 16:51:37 -0500]   start: my test
 *   [17-Feb-2009 16:51:37 -0500]     my message
 *   [17-Feb-2009 16:51:37 -0500]     shell exec: cp -r * /dev/null
 *   [17-Feb-2009 16:51:37 -0500]     start: my sql calls
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 *   [17-Feb-2009 16:51:37 -0500]     end: my sql calls - 0.0018 seconds shell exec: 1, sql: 3, 
 *   [17-Feb-2009 16:51:37 -0500]   end: my test - 0.0055 seconds shell exec: 1, sql: 3, 
 *   [17-Feb-2009 16:51:38 -0500] end: Task: mail.  - 0.8854 seconds shell exec: 1, sql: 3, 
 *
 * logs/sql (after reloading the main page):
 *
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 *   [17-Feb-2009 16:51:37 -0500]       sql: select * from example
 */
class debug_logger extends rcube_plugin
{
    protected $runlog;

    function init()
    {
        require_once(__DIR__ . '/runlog/runlog.php');

        $this->runlog = new runlog();

        if (!rcmail::get_instance()->config->get('log_dir')) {
            rcmail::get_instance()->config->set('log_dir', INSTALL_PATH . 'logs');
        }

        $log_config = rcmail::get_instance()->config->get('debug_logger', []);
        $log_dir    = rcmail::get_instance()->config->get('log_dir');

        foreach ($log_config as $type => $file) {
            $this->runlog->set_file($log_dir . '/' . $file, $type);
        }

        $start_string = '';
        $action = rcmail::get_instance()->action;
        $task   = rcmail::get_instance()->task;

        if ($action) {
            $start_string .= "Action: {$action}. ";
        }

        if ($task) {
            $start_string .= "Task: {$task}. ";
        }

        $this->runlog->start($start_string);

        $this->add_hook('console', [$this, 'console']);
        $this->add_hook('authenticate', [$this, 'authenticate']);
    }

    function authenticate($args)
    {
        $this->runlog->note('Authenticating '.$args['user'].'@'.$args['host']);
        return $args;
    }

    function console($args)
    {
        $note = $args['args'][0];

        if (!empty($args['args'][1])) {
            $type = $args['args'][1];
        }
        else {
            // This could be extended to detect types based on the
            // file which called console. For now only rcube_imap/rcube_storage is supported
            $bt   = debug_backtrace();
            $file = count($bt) >= 2 ? $bt[2]['file'] : '';

            switch (basename($file)) {
                case 'rcube_imap.php':
                    $type = 'imap';
                    break;
                case 'rcube_storage.php':
                    $type = 'storage';
                    break;
                default:
                    $type = false;
                    break;
            }
        }

        switch ($note) {
            case 'end':
                $type = 'end';
                break;
        }

        switch ($type) {
            case 'start':
                $this->runlog->start($note);
                break;
            case 'end':
                $this->runlog->end();
                break;
            default:
                $this->runlog->note($note, $type);
                break;
        }

        return $args;
    }

    function __destruct()
    {
        if ($this->runlog) {
            $this->runlog->end();
        }
    }
}
