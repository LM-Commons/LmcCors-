<?php

declare(strict_types=1);

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace LmcCors\Options;

use Laminas\Stdlib\AbstractOptions;

use function strtoupper;

/**
 * CorsOptions
 *
 * @template TValue
 * @extends AbstractOptions<TValue>
 */
class CorsOptions extends AbstractOptions
{
    public const ROUTE_PARAM = 'cors';

    /**
     * Set the list of allowed origins domain with protocol.
     */
    protected array $allowedOrigins = [];

    /**
     * Set the list of HTTP verbs.
     */
    protected array $allowedMethods = [];

    /**
     * Set the list of headers.
     */
    protected array $allowedHeaders = [];

    /**
     * Set the max age of the authorize request in seconds.
     */
    protected int $maxAge = 0;

    /**
     * Set the list of exposed headers.
     */
    protected array $exposedHeaders = [];

    /**
     * Allow CORS request with credential.
     */
    protected bool $allowedCredentials = false;

    public function setAllowedOrigins(array $allowedOrigins): void
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    public function setAllowedMethods(array $allowedMethods): void
    {
        foreach ($allowedMethods as &$allowedMethod) {
            $allowedMethod = strtoupper($allowedMethod);
        }

        $this->allowedMethods = $allowedMethods;
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function setAllowedHeaders(array $allowedHeaders): void
    {
        $this->allowedHeaders = $allowedHeaders;
    }

    public function getAllowedHeaders(): array
    {
        return $this->allowedHeaders;
    }

    public function setMaxAge(int $maxAge): void
    {
        $this->maxAge = (int) $maxAge;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    public function setExposedHeaders(array $exposedHeaders): void
    {
        $this->exposedHeaders = $exposedHeaders;
    }

    public function getExposedHeaders(): array
    {
        return $this->exposedHeaders;
    }

    public function setAllowedCredentials(bool $allowedCredentials): void
    {
        $this->allowedCredentials = $allowedCredentials;
    }

    public function getAllowedCredentials(): bool
    {
        return $this->allowedCredentials;
    }
}
