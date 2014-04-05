<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!file_exists('config.php'))
{
    die("Config file 'config.php' doesn't exist. Please use 'config.sample.php' to create one.");
}

require_once 'config.php';
require_once 'functions.php';

$simple_html_dom = __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'simple_html_dom.php';

require_once $simple_html_dom;

// use timestamp for content debugging
$timestamp = time();

// found entries on website
$entries = array();

// get real page count from html content after first request
$pages = 1;

// create url for the http requests based on the config search settings
$url_pattern = str_replace(
    array_keys($config['search']), 
    array_values($config['search']), 
    $config['url_pattern']
);

// get entries for all following pages
for ($i = 1; $i <= $pages; $i++)
{
    $url = str_replace('{page}', $i, $url_pattern);

    $content = get_content($url);
    $html = str_get_html($content);
    
    debug_content($i . '.html', $content, $timestamp);

    $entries_page = get_entries($html);
    $entries = $entries + $entries_page;

    // get page count only after first run
    if ($i === 1)
    {
        $ul_pager = $html->find('ul[data-is24-qa=paging_pages]');
        $pages = (int) $ul_pager[0]->last_child()->children(0)->innertext;
    }
    
    unset($content);
}

$db_entries = array();
$new_entries = array();
$new_entries_full = array();
$existing_entries = array();

try
{
    $db_file = $config['sqlite_file'];

    // create sqlite database
    if (!file_exists($db_file))
    {
        $created_db = require 'sqlite.init.php';

        if (!$created_db)
        {
            echo 'Error creating SQLite Database.';
            exit;
        }
    }

    $pdo = new PDO('sqlite:' . $db_file);
    
    $sql = 'SELECT * FROM entries';
    
    // get all db entry ids
    foreach ($pdo->query($sql) as $row)
    {
        $id = $row['id'];
        $title = $row['title'];

        $db_entries[$id] = array(
            'id' => $id,
            'title' => $title
        );
    }
    
    debug(array_keys($entries), 'Found Entries');
    debug(array_keys($db_entries), 'DB Entries');
        
    // look for new and existing entries
    // new entries have not same id AND title as an existing entry
    foreach ($entries as $id => $entry)
    {
        if (array_key_exists($id, $db_entries) && $db_entries[$id]['title'] === $entry['title'])
        {
            $existing_entries[] = $id;
        }
        else
        {
            $new_entries[] = $id;
        }
    }
    
    debug($existing_entries, 'Existing Entries');
    debug($new_entries, 'New Entries');
    
    // add new entries to db
    foreach ($new_entries as $id)
    {
        $entry = $entries[$id];
        $new_entries_full[$id] = $entry;

        $stmt = $pdo->prepare(
            // attributes: id, created_at, title, subtitle, price, area, rooms, url, picture_url
            'INSERT INTO entries VALUES (?, datetime(\'now\'), ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(
            array(
                $entry['id'], 
                $entry['title'], 
                $entry['subtitle'], 
                $entry['price'], 
                $entry['area'], 
                $entry['rooms'], 
                $entry['url'], 
                $entry['picture_url']
            )
        );
    }
    
    $output_body = render($config['template'], array('entries' => $new_entries_full));

    if (php_sapi_name() != 'cli')
    {
        header("Content-Type: text/html; charset=utf-8");
        echo $output_body;
    }
    else
    {
        if (count($new_entries_full) > 0)
        {
            if (count($argv) >= 2 && in_array('-console', $argv))
            {
                print_r($new_entries_full);
            }
            else
            {
                $mail_sender = $config['mail_sender'];
                $mail_receiver = $config['mail_receiver'];
                $mail_header = 'From: ' . $mail_sender . "\n" .
                               'Content-type: text/html; charset=utf-8' . "\r\n";
                $mail_subject = $config['mail_subject'];

                mail($mail_receiver, $mail_subject, $output_body, $mail_header);
            }
        }
    }
}
catch (Exception $exception)
{
    debug($exception, 'Exception');
    
    echo $exception;
}