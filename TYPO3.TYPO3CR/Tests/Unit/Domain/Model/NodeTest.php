<?php
namespace TYPO3\TYPO3CR\Tests\Unit\Domain\Model;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3CR".               *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */
use TYPO3\TYPO3CR\Domain\Model\Node;

/**
 * Testcase for the "Node" domain model
 */
class NodeTest extends \TYPO3\Flow\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function createNodeFromTemplateUsesWorkspaceFromContextForNodeData()
    {
        $workspace = $this->getMock('TYPO3\TYPO3CR\Domain\Model\Workspace', array(), array(), '', false);
        $parentNodeData = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeData', array(), array(), '', false);
        $newNodeData = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeData', array(), array(), '', false);

        $mockFirstLevelNodeCache = $this->getMock('TYPO3\TYPO3CR\Domain\Service\Cache\FirstLevelNodeCache');
        $newNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\Node', array(), array(), '', false);
        $context = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Service\Context')->disableOriginalConstructor()->getMock();
        $context->expects($this->any())->method('getFirstLevelNodeCache')->will($this->returnValue($mockFirstLevelNodeCache));
        $nodeTemplate = new \TYPO3\TYPO3CR\Domain\Model\NodeTemplate();

        $context->expects($this->any())->method('getWorkspace')->will($this->returnValue($workspace));

        $nodeFactory = $this->getMock('TYPO3\TYPO3CR\Domain\Factory\NodeFactory');

        $parentNode = new \TYPO3\TYPO3CR\Domain\Model\Node($parentNodeData, $context);

        $this->inject($parentNode, 'nodeFactory', $nodeFactory);

        $parentNodeData->expects($this->atLeastOnce())->method('createNodeDataFromTemplate')->with($nodeTemplate, 'bar', $workspace)->will($this->returnValue($newNodeData));
        $nodeFactory->expects($this->atLeastOnce())->method('createFromNodeData')->with($newNodeData, $context)->will($this->returnValue($newNode));

        $parentNode->createNodeFromTemplate($nodeTemplate, 'bar');
    }


    /**
     * @test
     */
    public function getPrimaryChildNodeReturnsTheFirstChildNode()
    {
        $mockNodeData = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Model\NodeData')->disableOriginalConstructor()->getMock();
        $mockNodeData->expects($this->any())->method('getPath')->will($this->returnValue('/foo/bar'));
        $mockContext = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Service\Context')->disableOriginalConstructor()->getMock();


        $node = new \TYPO3\TYPO3CR\Domain\Model\Node($mockNodeData, $mockContext);

        $mockNodeDataRepository = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository')->disableOriginalConstructor()->getMock();
        $this->inject($node, 'nodeDataRepository', $mockNodeDataRepository);

        $expectedNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
        $mockNodeDataRepository->expects($this->once())->method('findFirstByParentAndNodeTypeInContext')->with('/foo/bar', null, $mockContext)->will($this->returnValue($expectedNode));

        $primaryChildNode = $node->getPrimaryChildNode();

        $this->assertSame($expectedNode, $primaryChildNode);
    }

    /**
     * Data Provider for contextPathPatternShouldWorkWithContexts
     *
     * @return array
     */
    public function dataSourceForContextPathPattern()
    {
        return array(
            'empty node path' => array(
                'path' => '',
                'expected' => array(
                    0 => ''
                )
            ),
            'node path starting with /' => array(
                'path' => '/features',
                'expected' => array(
                    0 => '/features',
                    'NodePath' => '/features',
                    1 => '/features'
                )
            ),
            'simple context with no workspace' => array(
                'path' => 'features',
                'expected' => array(
                    0 => 'features',
                    'NodePath' => 'features',
                    1 => 'features'
                )
            ),
            'simple context with workspace' => array(
                'path' => 'features@user-admin',
                'expected' => array(
                    0 => 'features@user-admin',
                    'NodePath' => 'features',
                    1 => 'features',
                    'WorkspaceName' => 'user-admin',
                    2 => 'user-admin'
                )
            ),
            'simple dimension' => array(
                'path' => 'features@user-admin;language=de_DE,mul_ZZ',
                'expected' => array(
                    0 => 'features@user-admin;language=de_DE,mul_ZZ',
                    'NodePath' => 'features',
                    1 => 'features',
                    'WorkspaceName' => 'user-admin',
                    2 => 'user-admin',
                    'Dimensions' => 'language=de_DE,mul_ZZ',
                    3 => 'language=de_DE,mul_ZZ'
                )
            ),
            'multiple dimensions' => array(
                'path' => 'features@user-admin;language=de_DE,mul_ZZ&original=blah',
                'expected' => array(
                    0 => 'features@user-admin;language=de_DE,mul_ZZ&original=blah',
                    'NodePath' => 'features',
                    1 => 'features',
                    'WorkspaceName' => 'user-admin',
                    2 => 'user-admin',
                    'Dimensions' => 'language=de_DE,mul_ZZ&original=blah',
                    3 => 'language=de_DE,mul_ZZ&original=blah'
                )
            )
        );
    }

    /**
     * @test
     * @dataProvider dataSourceForContextPathPattern
     */
    public function contextPathPatternShouldWorkWithContexts($path, $expected)
    {
        $matches = array();
        preg_match(\TYPO3\TYPO3CR\Domain\Model\NodeInterface::MATCH_PATTERN_CONTEXTPATH, $path, $matches);

        $this->assertSame($expected, $matches);
    }

    /**
     * @test
     */
    public function createNodeWithAutoCreatedChildNodesAndNoIdentifierUsesGeneratedIdentifierOfNodeForChildNodes()
    {
        $mockContext = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Service\Context')->disableOriginalConstructor()->getMock();
        $mockContext->expects($this->any())->method('getTargetDimensions')->will($this->returnValue(array('language' => 'mul_ZZ')));
        $mockFirstLevelNodeCache = $this->getMock('TYPO3\TYPO3CR\Domain\Service\Cache\FirstLevelNodeCache');
        $mockContext->expects($this->any())->method('getFirstLevelNodeCache')->will($this->returnValue($mockFirstLevelNodeCache));

        $mockNodeData = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Model\NodeData')->disableOriginalConstructor()->getMock();
        $mockNodeType = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Model\NodeType')->disableOriginalConstructor()->getMock();
        $mockSubNodeType = $this->getMockBuilder('TYPO3\TYPO3CR\Domain\Model\NodeType')->disableOriginalConstructor()->getMock();

        $mockNodeType->expects($this->any())->method('getDefaultValuesForProperties')->will($this->returnValue(array()));
        $mockNodeType->expects($this->any())->method('getAutoCreatedChildNodes')->will($this->returnValue(array(
            'subnode1' => $mockSubNodeType
        )));

        $i = 0;
        $generatedIdentifiers = array();
        $node = $this->getMock('TYPO3\TYPO3CR\Domain\Model\Node', array('createSingleNode'), array($mockNodeData, $mockContext));
        $node->expects($this->any())->method('createSingleNode')->will($this->returnCallback(function () use (&$i, &$generatedIdentifiers, $mockSubNodeType) {
            $newNode = $this->getMock('TYPO3\TYPO3CR\Domain\Model\NodeInterface');
            $newNode->expects($this->any())->method('getIdentifier')->will($this->returnValue('node-' . $i++));

            $newNode->expects($this->once())->method('createNode')->with('subnode1', $mockSubNodeType, $this->callback(function ($identifier) use (&$generatedIdentifiers, $i) {
                $generatedIdentifiers[$i] = $identifier;
                return true;
            }));

            return $newNode;
        }));

        $node->createNode('foo', $mockNodeType);
        $node->createNode('bar', $mockNodeType);

        $this->assertNotSame($generatedIdentifiers[1], $generatedIdentifiers[2], 'Child nodes should have distinct identifiers');
    }
}
