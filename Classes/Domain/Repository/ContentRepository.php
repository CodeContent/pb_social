<?php

namespace PlusB\PbSocial\Domain\Repository;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

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

class ContentRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{

    public function initializeObject()
    {
        /** @var $defaultQuerySettings Typo3QuerySettings */
        $defaultQuerySettings = $this->objectManager->get(Typo3QuerySettings::class);

        // don't add the pid constraint
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
    }

    /**
     * @param string $ctype
     * @param string $list_type
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findFlexforms($ctype, $list_type)
    {
        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                array(
                    $query->equals('ctype', $ctype),
                    $query->equals('list_type', $list_type),
                )
            )
        );

        return $query->execute();
    }


}