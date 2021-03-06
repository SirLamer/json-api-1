<?php namespace Neomerx\JsonApi\Http;

/**
 * Copyright 2015-2018 info@neomerx.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use Neomerx\JsonApi\Contracts\Document\ErrorInterface;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Contracts\Http\Headers\HeaderParametersParserInterface;
use Neomerx\JsonApi\Contracts\Http\Headers\MediaTypeInterface;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Neomerx\JsonApi\Contracts\Schema\ContainerInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Neomerx\JsonApi
 */
abstract class BaseResponses implements ResponsesInterface
{
    /** Header name that contains format of input data from client */
    const HEADER_CONTENT_TYPE = HeaderParametersParserInterface::HEADER_CONTENT_TYPE;

    /** Header name that location of newly created resource */
    const HEADER_LOCATION = 'Location';

    /**
     * Create HTTP response.
     *
     * @param string|null $content
     * @param int         $statusCode
     * @param array       $headers
     *
     * @return mixed
     */
    abstract protected function createResponse(?string $content, int $statusCode, array $headers);

    /**
     * @return EncoderInterface
     */
    abstract protected function getEncoder(): EncoderInterface;

    /**
     * @return string|null
     */
    abstract protected function getUrlPrefix(): ?string;

    /**
     * @return EncodingParametersInterface|null
     */
    abstract protected function getEncodingParameters(): ?EncodingParametersInterface;

    /**
     * @return ContainerInterface
     */
    abstract protected function getSchemaContainer(): ?ContainerInterface;

    /**
     * @return MediaTypeInterface
     */
    abstract protected function getMediaType(): MediaTypeInterface;

    /**
     * @inheritdoc
     */
    public function getContentResponse(
        $data,
        int $statusCode = self::HTTP_OK,
        array $links = null,
        $meta = null,
        array $headers = []
    ) {
        $encoder = $this->getEncoder();
        $links === null ?: $encoder->withLinks($links);
        $meta === null ?: $encoder->withMeta($meta);
        $content = $encoder->encodeData($data, $this->getEncodingParameters());

        return $this->createJsonApiResponse($content, $statusCode, $headers, true);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedResponse($resource, array $links = null, $meta = null, array $headers = [])
    {
        $encoder = $this->getEncoder();
        $links === null ?: $encoder->withLinks($links);
        $meta === null ?: $encoder->withMeta($meta);
        $content = $encoder->encodeData($resource, $this->getEncodingParameters());
        $headers[self::HEADER_LOCATION] = $this->getResourceLocationUrl($resource);

        return $this->createJsonApiResponse($content, self::HTTP_CREATED, $headers, true);
    }

    /**
     * @inheritdoc
     */
    public function getCodeResponse(int $statusCode, array $headers = [])
    {
        return $this->createJsonApiResponse(null, $statusCode, $headers, false);
    }

    /**
     * @inheritdoc
     */
    public function getMetaResponse($meta, int $statusCode = self::HTTP_OK, array $headers = [])
    {
        $encoder = $this->getEncoder();
        $content = $encoder->encodeMeta($meta);

        return $this->createJsonApiResponse($content, $statusCode, $headers, true);
    }

    /**
     * @inheritDoc
     */
    public function getIdentifiersResponse(
        $data,
        int $statusCode = self::HTTP_OK,
        array $links = null,
        $meta = null,
        array $headers = []
    ) {
        $encoder = $this->getEncoder();
        $links === null ?: $encoder->withLinks($links);
        $meta === null ?: $encoder->withMeta($meta);
        $content = $encoder->encodeIdentifiers($data, $this->getEncodingParameters());

        return $this->createJsonApiResponse($content, $statusCode, $headers, true);
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getErrorResponse($errors, int $statusCode = self::HTTP_BAD_REQUEST, array $headers = [])
    {
        if ($errors instanceof ErrorCollection || is_array($errors) === true) {
            /** @var ErrorInterface[] $errors */
            $content = $this->getEncoder()->encodeErrors($errors);
        } else {
            /** @var ErrorInterface $errors */
            $content = $this->getEncoder()->encodeError($errors);
        }

        return $this->createJsonApiResponse($content, $statusCode, $headers, true);
    }

    /**
     * @param mixed $resource
     *
     * @return string
     */
    protected function getResourceLocationUrl($resource): string
    {
        $resSubUrl = $this->getSchemaContainer()->getSchema($resource)->getSelfSubLink($resource)->getSubHref();
        $urlPrefix = $this->getUrlPrefix();
        $location  = $urlPrefix . $resSubUrl;

        return $location;
    }

    /**
     * @param string|null $content
     * @param int         $statusCode
     * @param array       $headers
     * @param bool        $addContentType
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    protected function createJsonApiResponse(
        ?string $content,
        int $statusCode,
        array $headers = [],
        $addContentType = true
    ) {
        if ($addContentType === true) {
            $headers[self::HEADER_CONTENT_TYPE] = $this->getMediaType()->getMediaType();
        }

        return $this->createResponse($content, $statusCode, $headers);
    }
}
