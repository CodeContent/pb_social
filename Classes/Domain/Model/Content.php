<?php
namespace PlusB\PbSocial\Domain\Model;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Arend Maubach <am@plusb.de>, plus B
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class Content
 *
 * @package PlusB\PbSocial\Domain\Model
 */
class Content extends AbstractEntity
{
    /**
     * @var string
     */
    protected $ctype;

    /**
     * @var string
     */
    protected $piFlexform;

    /**
     * @return string
     */
    public function getCtype()
    {
        return $this->ctype;
    }

    /**
     * @param string $ctype
     */
    public function setCtype($ctype)
    {
        $this->ctype = $ctype;
    }

    /**
     * @return string
     */
    public function getPiFlexform()
    {
        return $this->piFlexform;
    }

    /**
     * @param string $piFlexform
     */
    public function setPiFlexform($piFlexform)
    {
        $this->piFlexform = $piFlexform;
    }




}