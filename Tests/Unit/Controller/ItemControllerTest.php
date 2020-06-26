<?php
namespace PlusB\PbSocial\Tests\Unit\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plus B
 *  			
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
 * Test case for class PlusB\PbSocial\Controller\ItemController.
 *
 * @author Mikolaj Jedrzejewski <mj@plusb.de>
 */
class ItemControllerTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{

    /**
     * @var \PlusB\PbSocial\Controller\ItemController
     */
    protected $subject = null;

    protected function setUp()
    {
        $this->subject = $this->getMock('PlusB\\PbSocial\\Controller\\ItemController', array('redirect', 'forward', 'addFlashMessage'), array(), '', false);
    }

    protected function tearDown()
    {
        unset($this->subject);
    }

    /**
     * @test
     */
    public function listActionFetchesAllItemsFromRepositoryAndAssignsThemToView()
    {
        $allItems = $this->getMock('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage', array(), array(), '', false);

        $itemRepository = $this->getMock('PlusB\\PbSocial\\Domain\\Repository\\ItemRepository', array('findAll'), array(), '', false);
        $itemRepository->expects($this->once())->method('findAll')->will($this->returnValue($allItems));
        $this->inject($this->subject, 'itemRepository', $itemRepository);

        $view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
        $view->expects($this->once())->method('assign')->with('items', $allItems);
        $this->inject($this->subject, 'view', $view);

        $this->subject->listAction();
    }

    /**
     * @test
     */
    public function showActionAssignsTheGivenItemToView()
    {
        $item = new \PlusB\PbSocial\Domain\Model\Item();

        $view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
        $this->inject($this->subject, 'view', $view);
        $view->expects($this->once())->method('assign')->with('item', $item);

        $this->subject->showAction($item);
    }

    /**
     * @test
     */
    public function editActionAssignsTheGivenItemToView()
    {
        $item = new \PlusB\PbSocial\Domain\Model\Item();

        $view = $this->getMock('TYPO3\\CMS\\Extbase\\Mvc\\View\\ViewInterface');
        $this->inject($this->subject, 'view', $view);
        $view->expects($this->once())->method('assign')->with('item', $item);

        $this->subject->editAction($item);
    }

    /**
     * @test
     */
    public function updateActionUpdatesTheGivenItemInItemRepository()
    {
        $item = new \PlusB\PbSocial\Domain\Model\Item();

        $itemRepository = $this->getMock('PlusB\\PbSocial\\Domain\\Repository\\ItemRepository', array('update'), array(), '', false);
        $itemRepository->expects($this->once())->method('update')->with($item);
        $this->inject($this->subject, 'itemRepository', $itemRepository);

        $this->subject->updateAction($item);
    }

    /**
     * @test
     */
    public function deleteActionRemovesTheGivenItemFromItemRepository()
    {
        $item = new \PlusB\PbSocial\Domain\Model\Item();

        $itemRepository = $this->getMock('PlusB\\PbSocial\\Domain\\Repository\\ItemRepository', array('remove'), array(), '', false);
        $itemRepository->expects($this->once())->method('remove')->with($item);
        $this->inject($this->subject, 'itemRepository', $itemRepository);

        $this->subject->deleteAction($item);
    }
}
