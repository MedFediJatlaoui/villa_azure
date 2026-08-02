<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace IAWPSCOPED\Symfony\Component\Translation\Loader;

use IAWPSCOPED\Symfony\Component\Translation\Exception\InvalidResourceException;
use IAWPSCOPED\Symfony\Component\Translation\Exception\NotFoundResourceException;
use IAWPSCOPED\Symfony\Component\Translation\MessageCatalogue;
/**
 * LoaderInterface is the interface implemented by all translation loaders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @internal
 */
interface LoaderInterface
{
    /**
     * Loads a locale.
     *
     * @throws NotFoundResourceException when the resource cannot be found
     * @throws InvalidResourceException  when the resource cannot be loaded
     */
    public function load(mixed $resource, string $locale, string $domain = 'messages') : MessageCatalogue;
}
