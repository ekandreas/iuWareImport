<?php

include 'updater.php';

if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
	$config = array(
		'slug' => 'iuwareimport', // this is the slug of your plugin
		'proper_folder_name' => 'iuwareimport', // this is the name of the folder your plugin lives in
		'api_url' => 'https://api.github.com/repos/ekandreas/iuwareimport', // the github API url of your github repo
		'raw_url' => 'https://raw.github.com/ekandreas/iuwareimport/master', // the github raw url of your github repo
		'github_url' => 'https://github.com/ekandreas/iuwareimport', // the github url of your github repo
		'zip_url' => 'https://github.com/ekandreas/iuwareimport/zipball/master', // the zip url of the github repo
		'sslverify' => true // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
        'requires' => '3.0', // which version of WordPress does your plugin require?
        'tested' => '3.5.1', // which version of WordPress is your plugin tested up to?
        'readme' => 'version.txt', // which file to use as the readme for the version number
        'access_token' => '', // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
    );
    new WPGitHubUpdater($config);
}

?>