<?php
namespace PlusB\PbSocial\Domain\Model;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plus B
 *  (c) 2019 Arend Maubach <am@plusb.de>, plus B
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
 * Item
 */
class Item extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{

    /**
     * @param string $type
     */
    public function __construct($type = '')
    {
        if ($this->getType() == '' && $type != '' && $type != null) {
            $this->setType($type);
        }

        $this->setDate(new \DateTime('now'));
    }

    /**
     * type
     *
     * @var string
     */
    protected $type = '';

    /**
     * itemIdentifier
     *
     * @var string
     */
    protected $itemIdentifier = '';

    /**
     * date
     *
     * @var \DateTime
     */
    protected $date = null;

    /**
     * result
     *
     * @var string
     */
    protected $result = '';

    /**
     * Returns the type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type
     *
     * @param string $type
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Returns the itemIdentifier
     *
     * @return string $itemIdentifier
     */
    public function getItemIdentifier()
    {
        return $this->itemIdentifier;
    }

    /**
     * Sets the cacheIdentifier
     *
     * @param string $itemIdentifier
     * @return void
     */
    public function setItemIdentifier($itemIdentifier)
    {
        $this->itemIdentifier = $itemIdentifier;
    }

    /**
     * Returns the date
     *
     * @return \DateTime $date
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Sets the date
     *
     * @param \DateTime $date
     * @return void
     */
    public function setDate(\DateTime $date)
    {
        $this->date = $date;
    }

    /**
     * Returns the result
     *
     * @return string $result
     */
    public function getResult()
    {
        return json_decode($this->result);
    }

    /**
     * Sets the result
     *
     * @param string $result
     * @return void
     */
    public function setResult($result)
    {
        $this->result = $result;
    }
}
