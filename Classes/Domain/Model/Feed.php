<?php
namespace PlusB\PbSocial\Domain\Model;

/***************************************************************
     *  Copyright notice
     *
     *  (c) 2016 Andre Wuttig <andr.wuttig@gmail.com>
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
 * Class Feed
 *
 */
class Feed
{

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $Provider;

    /**
     * @var string
     */
    protected $Image;

    /**
     * @var string
     */
    protected $Text;

    /**
     * @var int
     */
    protected $TimeStampTicks;

    /**
     * @var \DateTime
     */
    protected $creationDate;

    /**
     * @var string
     */
    protected $Link;

    /**
     * @var string
     */
    protected $Raw;

    /**
     * @param string $provider
     * @param string $rawFeed
     */
    public function __construct($provider, $rawFeed)
    {
        $this->setProvider($provider);
        $this->setRaw($rawFeed);
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $Image
     */
    public function setImage($Image)
    {
        if ($this->Provider == \PlusB\PbSocial\Controller\ItemController::TYPE_FACEBOOK) {
            if ($this->Raw->type == 'photo') {
                if (strpos($Image, '//scontent') !== false) {
                    //$Image = preg_replace('/\/v\/\S*\/p[0-9]*x[0-9]*\//', '/', $Image);
                }
                if (strpos($Image, '//fbcdn') !== false) {
                    //$Image = str_replace("/v/","/",$Image);
                    //$Image = str_replace("/p130x130/","/p/",$Image);
                }
            }
            if ($this->Raw->type == 'link') {
                $Image = preg_replace('/&[wh]=[0-9]*/', '', $Image); // for embedded links
            }
        }
        $this->Image = $Image;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->Image;
    }

    /**
     * @param string $Provider
     */
    public function setProvider($Provider)
    {
        $this->Provider = $Provider;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->Provider;
    }

    /**
     * @param string $Raw
     */
    public function setRaw($Raw)
    {
        $this->Raw = $Raw;
    }

    /**
     * @return string
     */
    public function getRaw()
    {
        return $this->Raw;
    }

    /**
     * @param string $Text
     */
    public function setText($Text)
    {
        $this->Text = $Text;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->Text;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @param \DateTime $creationDate
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;
    }

    /**
     * @param int $TimeStampTicks
     */
    public function setTimeStampTicks($TimeStampTicks)
    {
        $this->TimeStampTicks = $TimeStampTicks;
    }

    /**
     * @return int
     */
    public function getTimeStampTicks()
    {
        return $this->TimeStampTicks;
    }

    /**
     * @param string $Link
     */
    public function setLink($Link)
    {
        $this->Link = $Link;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->Link;
    }
}
