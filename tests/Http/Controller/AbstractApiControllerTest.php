<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Controller;

use Middag\Framework\Http\Controller\AbstractApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AbstractApiController::class)]
final class AbstractApiControllerTest extends TestCase
{
    #[Test]
    public function isJsonIsAlwaysTrue(): void
    {
        $this->assertTrue((new AbstractApiControllerTestController())->exposeIsJson());
    }

    #[Test]
    public function jsonResponseDerivesSuccessFromA2xxStatus(): void
    {
        $body = $this->decode((new AbstractApiControllerTestController())->exposeJsonResponse(['id' => 1]));

        $this->assertSame(['id' => 1, 'success' => true], $body);
    }

    #[Test]
    public function jsonResponseMarksNon2xxAsUnsuccessful(): void
    {
        $body = $this->decode(
            (new AbstractApiControllerTestController())->exposeJsonResponse(['reason' => 'bad'], Response::HTTP_UNPROCESSABLE_ENTITY),
        );

        $this->assertFalse($body['success']);
    }

    #[Test]
    public function jsonResponsePreservesAnExplicitSuccessFlag(): void
    {
        $body = $this->decode(
            (new AbstractApiControllerTestController())->exposeJsonResponse(['success' => true, 'note' => 'ok'], Response::HTTP_INTERNAL_SERVER_ERROR),
        );

        $this->assertTrue($body['success']);
    }

    #[Test]
    public function jsonResponseBoxesScalarDataUnderADataKey(): void
    {
        $body = $this->decode((new AbstractApiControllerTestController())->exposeJsonResponse('hello'));

        $this->assertSame(['data' => 'hello', 'success' => true], $body);
    }

    #[Test]
    public function errorResponseEmitsTheStandardEnvelopeWithoutDebugByDefault(): void
    {
        $response = (new AbstractApiControllerTestController())->exposeErrorResponse('Nope');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Nope', 'error_code' => 400],
            $this->decode($response),
        );
    }

    #[Test]
    public function errorResponseAttachesDebugWhenProvided(): void
    {
        $response = (new AbstractApiControllerTestController())->exposeErrorResponse('Boom', Response::HTTP_INTERNAL_SERVER_ERROR, ['trace' => 'x']);

        $body = $this->decode($response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['trace' => 'x'], $body['debug']);
    }

    #[Test]
    public function createdReturns201WithEnvelope(): void
    {
        $response = (new AbstractApiControllerTestController())->exposeCreated(['id' => 9]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['id' => 9, 'success' => true], $this->decode($response));
    }

    #[Test]
    public function noContentReturnsAnEmpty204(): void
    {
        $response = (new AbstractApiControllerTestController())->exposeNoContent();

        $this->assertNotInstanceOf(JsonResponse::class, $response);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function statusHelpersEmitTheirRespectiveErrorEnvelopes(): void
    {
        $controller = new AbstractApiControllerTestController();

        $notFound = $controller->exposeNotFound();
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertSame('Resource not found', $this->decode($notFound)['message']);

        $forbidden = $controller->exposeForbidden();
        $this->assertSame(403, $forbidden->getStatusCode());
        $this->assertSame('Access denied', $this->decode($forbidden)['message']);

        $unauthorized = $controller->exposeUnauthorized('Token expired');
        $this->assertSame(401, $unauthorized->getStatusCode());
        $this->assertSame('Token expired', $this->decode($unauthorized)['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($decoded);

        // @var array<string, mixed> $decoded
        return $decoded;
    }
}

/**
 * @internal
 */
final class AbstractApiControllerTestController extends AbstractApiController
{
    public function exposeIsJson(): bool
    {
        return $this->isJson();
    }

    public function exposeJsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->jsonResponse($data, $status);
    }

    public function exposeErrorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $debug = null): JsonResponse
    {
        return $this->errorResponse($message, $status, $debug);
    }

    public function exposeCreated(mixed $data = []): JsonResponse
    {
        return $this->created($data);
    }

    public function exposeNoContent(): Response
    {
        return $this->noContent();
    }

    public function exposeNotFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->notFound($message);
    }

    public function exposeForbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->forbidden($message);
    }

    public function exposeUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->unauthorized($message);
    }
}
