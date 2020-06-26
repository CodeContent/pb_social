<?php
namespace PlusB\PbSocial\Domain\Model;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
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
 * Credential
 */
class Credential extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{

    /**
     * @param string $type
     */
    public function __construct($type = '', $appId)
    {
        if ($this->getType() == '' && $type != '' && $type != null) {
            $this->setType($type);
        }

        $this->appId = $appId;

        $this->setExpirationDate(new \DateTime('now'));
        $this->setAccessToken('');
        $this->setValid(false);
    }

    /**
     * type
     *
     * @var string
     */
    protected $type;

    /**
     * appId
     *
     * @var string
     */
    protected $appId;

    /**
     * date
     *
     * @var \DateTime
     */
    protected $expirationDate;

    /**
     * result
     *
     * @var string
     */
    protected $accessToken;

    /**
     * isValid
     *
     * @var bool
     */
    protected $valid;

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
     * Returns the cacheIdentifier
     *
     * @return string $cacheIdentifier
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * Sets the cacheIdentifier
     *
     * @param string $appId
     * @return void
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * Returns the date
     *
     * @return \DateTime $date
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * Sets the date
     *
     * @param \DateTime $expirationDate
     * @return void
     */
    public function setExpirationDate(\DateTime $expirationDate)
    {
        $this->expirationDate = $expirationDate;
    }

    /**
     * Returns the result
     *
     * @return string $result
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Sets the result
     *
     * @param string $accessToken
     * @return void
     */
    public function setAccessToken($accessToken)
    {
        $accessToken = (string) $accessToken;

        if ($accessToken != '') {
            $this->setValid(true);
        }

        $this->accessToken = $accessToken;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        if ($this->getAccessToken() == '') {
            return false;
        }

        return $this->valid;
    }

    /**
     * @param bool $valid
     */
    public function setValid($valid)
    {
        $this->valid = $valid;
    }
}
