<?php

namespace DDTrace\Tests\Integrations\Symfony\V4_2;

use DDTrace\Tag;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Symfony/Version_4_2/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_TRACE_DEBUG' => 'true',
            'DD_SERVICE' => 'test_symfony_42',
        ]);
    }

    /**
     * @dataProvider provideSpecs
     * @param RequestSpec $spec
     * @param array $spanExpectations
     * @throws \Exception
     */
    public function testScenario(RequestSpec $spec, array $spanExpectations)
    {
        $traces = $this->tracesFromWebRequest(function () use ($spec) {
            $this->call($spec);
        });

        $this->assertFlameGraph($traces, $spanExpectations);
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_42',
                        'web',
                        'simple'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@simpleAction',
                        'symfony.route.name' => 'simple',
                        'http.route' => '/simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple?key=value&<redacted>',
                        'http.status_code' => '200',
                        Tag::SPAN_KIND => 'server',
                        Tag::COMPONENT => 'symfony',
                    ])->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request')->withChildren([
                                    SpanAssertion::exists('symfony.security.authentication.success'),
                                ]),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::exists('symfony.kernel.response'),
                                SpanAssertion::exists('symfony.kernel.finish_request'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_42',
                                    'web',
                                    'App\Controller\CommonScenariosController::simpleAction'
                                )->withExactTags([
                                    Tag::COMPONENT => 'symfony',
                                ])
                            ]),
                        ]),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_42',
                        'web',
                        'simple_view'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@simpleViewAction',
                        'symfony.route.name' => 'simple_view',
                        'http.route' => '/simple_view',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view?key=value&<redacted>',
                        'http.status_code' => '200',
                        Tag::SPAN_KIND => 'server',
                        Tag::COMPONENT => 'symfony',
                    ])->withChildren([
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request')->withChildren([
                                    SpanAssertion::exists('symfony.security.authentication.success'),
                                ]),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_42',
                                    'web',
                                    'App\Controller\CommonScenariosController::simpleViewAction'
                                )->withExactTags([
                                    Tag::COMPONENT => 'symfony',
                                ])->withChildren([
                                    SpanAssertion::build(
                                        'symfony.templating.render',
                                        'test_symfony_42',
                                        'web',
                                        'Twig\Environment twig_template.html.twig'
                                    )->withExactTags([
                                        Tag::COMPONENT => 'symfony',
                                    ]),
                                ]),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::exists('symfony.kernel.response'),
                                SpanAssertion::exists('symfony.kernel.finish_request'),
                            ]),
                        ]),
                        SpanAssertion::exists('symfony.kernel.terminate'),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_42',
                        'web',
                        'error'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@errorAction',
                        'symfony.route.name' => 'error',
                        'http.route' => '/error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error?key=value&<redacted>',
                        'http.status_code' => '500',
                        Tag::SPAN_KIND => 'server',
                        Tag::COMPONENT => 'symfony',
                    ])->setError('Exception', 'An exception occurred')
                    ->withExistingTagsNames(['error.stack'])
                    ->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request')->withChildren([
                                    SpanAssertion::exists('symfony.security.authentication.success'),
                                ]),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_42',
                                    'web',
                                    'App\Controller\CommonScenariosController::errorAction'
                                )->withExactTags([
                                    Tag::COMPONENT => 'symfony',
                                ])->setError('Exception', 'An exception occurred')
                                ->withExistingTagsNames(['error.stack']),
                                SpanAssertion::exists('symfony.kernel.handleException')->withChildren([
                                    SpanAssertion::exists('symfony.kernel.exception')->withChildren([
                                        SpanAssertion::exists('symfony.kernel.request'),
                                        SpanAssertion::exists('symfony.kernel.controller'),
                                        SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                        SpanAssertion::build(
                                            'symfony.controller',
                                            'test_symfony_42',
                                            'web',
                                            'Symfony\Bundle\TwigBundle\Controller\ExceptionController::showAction'
                                        )->withExactTags([
                                            Tag::COMPONENT => 'symfony',
                                        ])->withChildren([
                                            SpanAssertion::build(
                                                'symfony.templating.render',
                                                'test_symfony_42',
                                                'web',
                                                'Twig\Environment @Twig/Exception/error.html.twig'
                                            )->withExactTags([
                                                Tag::COMPONENT => 'symfony',
                                            ]),
                                        ]),
                                        SpanAssertion::exists('symfony.kernel.response'),
                                        SpanAssertion::exists('symfony.kernel.finish_request'),
                                    ]),
                                    SpanAssertion::exists('symfony.kernel.response'),
                                    SpanAssertion::exists('symfony.kernel.finish_request'),
                                ]),
                            ]),
                        ]),
                    ]),
                ],
                'A GET request to a missing route' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_42',
                        'web',
                        'GET /does_not_exist'
                    )->withExactTags([
                        'symfony.route.action' => 'Symfony\Bundle\TwigBundle\Controller\ExceptionController@showAction',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/does_not_exist?key=value&<redacted>',
                        'http.status_code' => '404',
                        Tag::SPAN_KIND => 'server',
                        Tag::COMPONENT => 'symfony',
                    ])->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.handleException')->withChildren([
                                    SpanAssertion::exists('symfony.kernel.finish_request'),
                                    SpanAssertion::exists('symfony.kernel.response'),
                                    SpanAssertion::exists('symfony.kernel.exception')->withChildren([
                                        SpanAssertion::exists('symfony.kernel.request'),
                                        SpanAssertion::exists('symfony.kernel.controller'),
                                        SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                        SpanAssertion::build(
                                            'symfony.controller',
                                            'test_symfony_42',
                                            'web',
                                            'Symfony\Bundle\TwigBundle\Controller\ExceptionController::showAction'
                                        )->withExactTags([
                                            Tag::COMPONENT => 'symfony',
                                        ])->withChildren([
                                            SpanAssertion::build(
                                                'symfony.templating.render',
                                                'test_symfony_42',
                                                'web',
                                                'Twig\Environment @Twig/Exception/error.html.twig'
                                            )->withExactTags([
                                                Tag::COMPONENT => 'symfony',
                                            ]),
                                        ]),
                                        SpanAssertion::exists('symfony.kernel.response'),
                                        SpanAssertion::exists('symfony.kernel.finish_request'),
                                    ]),
                                ]),
                                SpanAssertion::exists('symfony.kernel.request')
                                    ->setError(
                                        'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
                                        'No route found for "GET /does_not_exist"'
                                    )
                                    ->withExistingTagsNames(['error.stack']),
                            ]),
                        ]),
                    ]),
                ],
            ]
        );
    }
}
