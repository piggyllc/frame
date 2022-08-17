<?php

/*
 * To use Twig rendering, ensure your composer.json file contains
 * the following:
 *
 * "require": {
 *      "twig/twig": "~1.0"
 * }
 *
 * You'll also need to add a world writable /cache folder under the
 * project's /Views folder
 */

namespace Frame\Response;

use Frame\Core\Context;
use Frame\Response\Exception\ResponseConfigException;

class Twig extends Foundation implements ResponseInterface
{

    protected $contentType = 'text/html';
    protected $defaultExtension = '.html.twig';
    protected $debug = true;

    public function __construct(Context $context)
    {

        // Check that Twig is loaded
        if (!class_exists('Twig\Loader\FilesystemLoader')) {
            throw new ResponseConfigException("Twig is not installed, Response class cannot be used.");
        }

        if ($context->getProject()->debugMode !== null) {
            $this->debug = $context->getProject()->debugMode;
        }

        parent::__construct($context);

    }

    public function render($params = null)
    {

        if (!$this->viewFilename) {
            throw new ResponseConfigException("Twig Response class cannot determine view filename. Please set using \$response->setViewFilename()");
        }

        // Instantiate the Twig library only once, keep it global
        if (!isset($this->context->getProject()->config->twig)) {

            // Make sure we can determine which template to render
            if (!$this->viewDir) {
                throw new ResponseConfigException("Twig Response class cannot determine view file/path automatically. Please set using \$response->setView()");
            }

            // Check for existence of cache directory before instantiating Twig
            $cacheDir = $this->viewDir . '/cache';
            if ((!file_exists($cacheDir)) || (!is_writable($cacheDir))) {
                throw new ResponseConfigException("Twig Response class requires the directory " . $cacheDir . ', and it must be writable');
            }

            // Initialize Twig
            $this->context->getProject()->config->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($this->viewDir), [
                'cache' => $this->viewDir . '/cache',
                'debug' => $this->debug
            ]);

        }

        // Ensure Twig has support for custom functions
        $this->context->getProject()->config->twig->addFunction(
            new \Twig\TwigFunction('urlFor', [$this, 'urlFor'])
        );
        if ($this->context->getProject()->debugMode) {
            $this->context->getProject()->config->twig->addFunction(
                new \Twig\TwigFunction('dump', 'var_dump')
            );
        }
        // Set a few global parameters
        $this->context->getProject()->config->twig->addGlobal('frame', [
            'project' => $this->context->getProject(),
            'url' => $this->context->getUrl()
        ]);

        $params = ($params != null ? $params : $this->viewParams);

        if (!headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: ' . $this->contentType);
        }

        // Render a view file with a .twig extension
        $twig = $this->context->getProject()->config->twig;
        $view = $this->viewFilename . (strpos($this->viewFilename, '.') === false ? $this->defaultExtension : '');

        echo $twig->render($view, (is_array($params) ? $params : []));

    }

}
