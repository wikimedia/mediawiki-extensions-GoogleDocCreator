<?php

class SpecialGoogleDocCreator extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GoogleDocCreator', 'googledoccreator' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$googleCredsPath = $this->getConfig()->get( "GoogleApiClientCredentialsPath" );
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();

		if ( !class_exists( "Google_Client" ) ) {
			$out->addHTML( '<div class="errorbox">You must install Google_Client. 
			Run "composer update" from your main directory.</div>' );
			return;
		}
		$user = $this->getContext()->getUser();
		if ( !$user->isAllowed( 'delete' ) ) {
			$this->displayRestrictionError();
			return;
		}
		// Get the API client and construct the service object.
		$client = new Google_Client();
		$client->setApplicationName( 'Google Doc Creator MediaWiki Extension' );
		$client->setScopes( Google_Service_Drive::DRIVE );
		$client->setAuthConfig( $googleCredsPath );
		$client->setAccessType( 'offline' );
		$client->setRedirectUri( $this->getPageTitle()->getFullURL() );
		$cache_object = ObjectCache::getInstance( CACHE_DB );
		$accessToken = $cache_object->get( "google-doc-creator-access-token" );
		if ( empty( $accessToken ) ) {
			if ( empty( $request->getVal( 'code' ) ) ) {
				// Request authorization from the user.
				$authUrl = $client->createAuthUrl();
				$formOpts = [
					'id' => 'get_auth_code',
					'method' => 'post',
					'action' => $this->getPageTitle()->getFullURL()
				];
				$out->addHTML(
					Html::openElement( 'form', $formOpts ) . "<br>" .
					Html::label( $this->msg( "googledocs-enter-authcode" ), "", [ "for" => "auth_code" ] ) .
					"<br><br>" . Html::element( 'input',
					[ "id" => "auth_code", "name" => "code", "type" => "text" ] ) .
					Html::element( 'a', [ "href" => $authUrl, "target" => "_blank" ],
					$this->msg( "googledocs-get-authcode-btn" ) ) . "<br><br>"
				);
				$out->addHTML(
					Html::submitButton( $this->msg( "htmlform-submit" ), [] ) .
					Html::closeElement( 'form' )
				);
				return;
			} else {
				$authCode = $request->getVal( 'code' );
			}

			// Exchange authorization code for an access token.
			$accessToken = $client->fetchAccessTokenWithAuthCode( $authCode );

			// Check to see if there was an error.
			if ( array_key_exists( 'error', $accessToken ) ) {
				throw new MWException( implode( ', ', $accessToken ) );
			}

			$cache_object->set( "google-doc-creator-access-token", $accessToken, 600 );
		}
		$client->setAccessToken( $accessToken );

		// Refresh the token if it's expired.
		if ( $client->isAccessTokenExpired() ) {
			$client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
			$cache_object->set( "google-doc-creator-access-token", $client->getAccessToken(), 600 );
		}

		if ( empty( $request->getVal( 'wikipage_name' ) ) ) {
			$formOpts = [
				'id' => 'get_wikipage_name',
				'method' => 'post',
				'action' => $this->getPageTitle()->getFullUrl()
			];
			$out->addHTML(
				Html::openElement( 'form', $formOpts ) . "<br>" .
				Html::label( $this->msg( "googledocs-enter-title" ), "", [ "for" => "wikipage_name" ] ) . "<br><br>" .
				Html::element( 'input', [ "id" => "wikipage_name",
				"name" => "wikipage_name", "type" => "text" ] ) . "<br><br>"
			);
			$out->addHTML(
				Html::submitButton( $this->msg( "htmlform-submit" ), [] ) .
				Html::closeElement( 'form' )
			);
			return;
		} else {
			$wikipage_name = $request->getVal( 'wikipage_name' );
		}
		$service = new Google_Service_Drive( $client );

		// Create the file
		$file = new Google_Service_Drive_DriveFile();
		$file->setName( $wikipage_name );
		$file->setMimeType( 'application/vnd.google-apps.document' );
		$file = $service->files->create( $file );

		$fileId = $file->getId();

		// Give everyone permission to read and write the file
		$permission = new Google_Service_Drive_Permission();
		$permission->setRole( 'writer' );
		$permission->setType( 'anyone' );
		$service->permissions->create( $fileId, $permission );

		$title = Title::newFromText( $wikipage_name );
		$article = new Article( $title );
		$content = new WikitextContent( '<googledocument id="' . $fileId . '" />' );
		$editSummary = $this->msg( "googledocs-edit-summary" )->inContentLanguage();
		$article->getPage()->doUserEditContent( $content, $user, $editSummary );
		$out->addHTML( $this->msg( "googledocs-success" ) .
		Linker::linkKnown( Title::newFromText( $wikipage_name ),
		$wikipage_name, [ 'target' => '_blank' ] ) );
	}

	/**
	 * Hook for parsing google document tag
	 * @param Parser $parser
	 */
	public static function setParserHook( Parser $parser ) {
		$parser->setHook( 'googledocument', 'SpecialGoogleDocCreator::renderDocument' );
	}

	/**
	 * Embedding Google Document into the page body
	 * @param string $input
	 * @param array $args
	 * @return string
	 */
	public static function renderDocument( $input, array $args ) {
		return Html::element(
			'iframe',
			[
				'src' => 'https://docs.google.com/document/d/' . rawurlencode( $args['id'] ),
				'width' => '100%',
				'height' => '1000px',
				'frameBorder' => 0
			],
			''
		);
	}
}
