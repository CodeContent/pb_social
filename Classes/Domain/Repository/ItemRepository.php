<?php
namespace PlusB\PbSocial\Domain\Repository;


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
 * The repository for Items
 */
class ItemRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{

    /**
     * @param string $type
     * @param string $itemIdentifier
     * @return object|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findByTypeAndItemIdentifier($type, $itemIdentifier)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                array(
                    $query->like('type', $type),
                    $query->equals('itemIdentifier', $itemIdentifier
                    )
                )
            )
        );
        return $query->execute()->getFirst();
    }

    /**
     * @param $item
     */
    public function saveItem($item)
    {
        $this->add($item);
        $this->persistenceManager->persistAll();
    }

    /**
     * @param $item
     */
    public function updateItem($item)
    {
        $this->update($item);
        $this->persistenceManager->persistAll();
    }
}
