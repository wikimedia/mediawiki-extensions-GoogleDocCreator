# GoogleDocCreator

Creates a Google Doc in your Drive and embeds it to a wiki page

## Installation

Download API credentials from https://developers.google.com/drive/api/v3/quickstart/php

Set the path to your credentials.json file:

    $wgGoogleApiClientCredentialsPath = "$IP/extensions/GoogleDocCreator/credentials.json";

Download this repo on your extensions folder

Add the following on your LocalSettings.php: 

    wfLoadExtension( 'GoogleDocCreator' );

Use composer to install dependencies. Run the following command from your main MediaWiki folder:

    composer update

## Usage

Visit the special page Special:GoogleDocCreator on your wiki. You must be logged in as a sysop user.
