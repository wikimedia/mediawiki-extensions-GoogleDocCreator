<?php

namespace MediaWiki\Extension\GoogleDocCreator;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;

class Hooks implements ParserFirstCallInitHook {
	/**
	 * Hook for parsing google document tag
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'googledocument', [ self::class, 'renderDocument' ] );
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
