<?php

namespace Askvortsov\FlarumSAML\Controllers;

use Flarum\Extension\ExtensionManager;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\IdPMetadataParser;

abstract class BaseSAMLController
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @var ResponseFactory
     */
    protected $response;

    protected $extensions;

    public function __construct(ResponseFactory $response, SettingsRepositoryInterface $settings, ExtensionManager $extensions, UrlGenerator $url)
    {
        $this->response = $response;
        $this->settings = $settings;
        $this->extensions = $extensions;
        $this->url = $url;
    }

    public function auth(): Auth {
        static $instance;
        if (!empty($instance)) {
            return $instance;
        }
        try {
            /**
             * Filters the XML metadata for IdP authority
             *
             * @return string XML string for IdP metadata
             */
            try {
                $idp_metadata_url  = trim($this->settings->get('askvortsov-saml.idp_metadata_url', ''));
                $idp_xml = file_get_contents($idp_metadata_url);
                $settings = IdPMetadataParser::parseXML($idp_xml);
            } catch (\Exception $e) {
                $idp_xml  = trim($this->settings->get('askvortsov-saml.idp_metadata', ''));
                $settings = IdPMetadataParser::parseXML($idp_xml);
            }
            $settings = IdPMetadataParser::parseXML($idp_xml);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        $settings['sp'] = [
            'entityId'                 => $this->url->to('forum')->route('askvortsov-saml.metadata'),
            'assertionConsumerService' => [
                'url' => $this->url->to('forum')->route('askvortsov-saml.acs'),
            ],
            'singleLogoutService'      => [
                'url' => $this->url->to('forum')->route('askvortsov-saml.logout'),
            ],
            'NameIDFormat'             => $this->settings->get('askvortsov-saml.nameid_format', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
        ];
        $settings['security'] = [
            'wantMessagesSigned' => true,
            'wantAssertionsSigned' => true,
        ];

        if (empty($instance)) {
            $instance = new Auth($settings);
        }

        return $instance;
    }
}
