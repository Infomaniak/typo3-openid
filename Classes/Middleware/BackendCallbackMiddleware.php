<?php
namespace Infomaniak\Auth\Middleware;

use Infomaniak\Auth\Service\AuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;

class BackendCallbackMiddleware implements MiddlewareInterface
{
    const string BACKEND_CALLBACK_PATH = 'loginInfomaniakAuthCallback';
    /**
     * Checks if the current request is a callback from the Infomaniak login process and if so, processes the
     * request to redirect to the TYPO3 login page with the appropriate parameters.
     *
     * @param ServerRequestInterface $request The incoming HTTP request.
     * @param RequestHandlerInterface $handler The next handler in the middleware pipeline.
     * @return ResponseInterface The response after processing the request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // Retrieve the query parameters from the incoming request's URL
        $queryParams = $request->getQueryParams();

        // Check if the URL path contains 'loginInfomaniakAuthCallback',
        // there is no error in query parameters, and both 'code' and 'state' parameters are provided
        if (
            str_contains($request->getUri()->getPath(), self::BACKEND_CALLBACK_PATH) &&
            !isset($queryParams['error']) &&
            isset($queryParams['code']) &&
            isset($queryParams['state'])
        ) {
            // Inject an additional parameter, 'loginProvider', with a specific value
            $queryParams['loginProvider'] = AuthenticationService::AUTH_INFOMANIAK_CODE;
            // Build the TYPO3 login URL by appending query parameters to the base '/typo3/login' path
            $loginUrl = AuthenticationService::buildSimpleUrl($request, '/typo3/login', $queryParams);

            // Create a redirect response to the TYPO3 login URL
            return new HtmlResponse(sprintf('<html lang="en">
                <head>
                    <title>Redirecting...</title>
                </head>
                <body>
                    <h1>Redirecting...</h1>
                    <div>You will be redirected in a moment. If you are not redirected, click the following link: <a id="link" href="%s">Go Now</a></div>
                    <script type="text/javascript">
                        var host = "%s";
                        document.getElementById("link").setAttribute("href", host);
                        setTimeout(function(){
                            window.location.href = host;
                        }, 500);
                    </script>
                </body>
            </html>', $loginUrl, $loginUrl), 200);

        }
        // If the above conditions are not met, pass the request to the next middleware/handler
        return $handler->handle($request);
    }
}
