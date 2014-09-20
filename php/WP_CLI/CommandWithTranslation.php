<?php

namespace WP_CLI;

/**
 * Base class for WP-CLI commands that deal with translations
 *
 * @package wp-cli
 */
abstract class CommandWithTranslation extends \WP_CLI_Command {

	protected $obj_type;

	protected $obj_fields = array(
		'language',
		'english_name',
		'native_name',
		'status',
		'updated',
		);

	/**
	 * List all languages available.
	 *
 	 * [--<field>=<value>]
	 * : Filter results based on the value of a field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each translation:
	 *
	 * * language
	 * * english_name
	 * * native_name
	 * * status
	 * * updated
	 *
	 * These fields are optionally available:
	 *
	 * * version
	 * * package
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$translations = $this->_get_all_languages();
		$available = $this->_get_installed_languages();

		$current_locale = get_locale();
		$translations = array_map( function( $translation ) use ( $available, $current_locale ) {
			$translation['status'] = ( in_array( $translation['language'], $available ) ) ? 'installed' : 'uninstalled';
			if ( $current_locale == $translation['language'] ) {
				$translation['status'] = 'active';
			}
			return $translation;
		}, $translations );

		foreach ( $translations as $key => $translation ) {
			foreach ( $this->obj_fields as $field ) {
				if ( isset( $assoc_args[$field] ) && $assoc_args[$field] != $translation[$field] ) {
					unset( $translations[$key] );
				}
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );
	}

	/**
	 * Callback to sort array by a 'language' key.
	 */
	protected function _sort_translations_callback( $a, $b ) {
		return strnatcasecmp( $a['language'], $b['language'] );
	}

	/**
	 * Install a given language.
	 *
	 * <language>
	 * : Language code to install.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = $this->_get_installed_languages();

		if ( in_array( $language_code, $available ) ) {
			\WP_CLI::warning( "Language already installed." );
			exit;
		}

		require_once ABSPATH . '/wp-admin/includes/translation-install.php';

		$response = wp_download_language_pack( $language_code );
		if ( $response == $language_code ) {
			\WP_CLI::success( "Language installed." );
		} else {
			\WP_CLI::error( "Couldn't install language." );
		}

	}

	/**
	 * Activate a given language.
	 *
	 * <language>
	 * : Language code to activate.
	 *
	 * @subcommand activate
	 */
	public function activate( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = $this->_get_installed_languages();

		if ( ! in_array( $language_code, $available ) ) {
			\WP_CLI::error( "Language not installed." );
		}

		if ( $language_code == 'en_US' ) {
			$language_code = '';
		}

		update_option( 'WPLANG', $language_code );
		\WP_CLI::success( "Language activated." );
	}

	/**
	 * Return a list of installed languages.
	 *
	 * @return array
	 */
	protected function _get_installed_languages() {
		$available = wp_get_installed_translations( $this->obj_type );
		$available = ! empty( $available['default'] ) ? array_keys( $available['default'] ) : array();
		$available[] = 'en_US';

		return $available;
	}

	/**
	 * Return a list of all languages
	 *
	 * @return array
	 */
	protected function _get_all_languages() {
		require_once ABSPATH . '/wp-admin/includes/translation-install.php';

		$response = translations_api( $this->obj_type );
		$translations = ! empty( $response['translations'] ) ? $response['translations'] : array();

		$en_us = array(
			'language' => 'en_US',
			'english_name' => 'English (United States)',
			'native_name' => 'English (United States)',
		);

		array_push( $translations, $en_us );
		uasort( $translations, array( $this, '_sort_translations_callback' ) );

		return $translations;
	}

	/**
	 * Uninstall a given language.
	 *
	 * <language>
	 * : Language code to uninstall.
	 *
	 * @subcommand uninstall
	 */
	public function uninstall( $args, $assoc_args ) {
		global $wp_filesystem;

		list( $language_code ) = $args;

		$available = $this->_get_installed_languages();

		if ( ! in_array( $language_code, $available ) ) {
			\WP_CLI::error( "Language not installed." );
		}

		$dir = 'core' === $this->obj_type ? '' : "/$this->obj_type";
		$files = scandir( WP_LANG_DIR . $dir );
		if ( ! $files ) {
			\WP_CLI::error( "No files found in language directory." );
		}

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();
		$deleted = false;
		foreach ( $files as $file ) {
			if ( '.' === $file[0] || is_dir( $file ) ) {
				continue;
			}
			$extension_length = strlen( $language_code ) + 4;
			$ending = substr( $file, -$extension_length );
			if ( ! in_array( $file, array( $language_code . '.po', $language_code . '.mo' ) ) && ! in_array( $ending, array( '-' . $language_code . '.po', '-' . $language_code . '.mo' ) ) ) {
				continue;
			}
			$deleted = $wp_filesystem->delete( WP_LANG_DIR . $dir . '/' . $file );
		}

		if ( $deleted ) {
			\WP_CLI::success( "Language uninstalled." );
		} else {
			\WP_CLI::error( "Couldn't uninstall language." );
		}

	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}

}
