<?php
/*
 * Copyright Blackbit digital Commerce GmbH <info@blackbit.de>
 *
 *  This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.
 *  This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

namespace Blackbit\PimcoreBundleManager\Tools;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

class InstallerKernel
{
    use MicroKernelTrait;

    /**
     * @var string
     */
    private $projectRoot;

    public function __construct(string $projectRoot, string $environment, bool $debug)
    {
        $this->projectRoot = $projectRoot;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getProjectDir()// : string
    {
        return PIMCORE_PROJECT_ROOT;
    }

    /**
     * @return string
     */
    public function getBundlesConfigFile() {
        return $this->getBundlesPath();
    }
}
