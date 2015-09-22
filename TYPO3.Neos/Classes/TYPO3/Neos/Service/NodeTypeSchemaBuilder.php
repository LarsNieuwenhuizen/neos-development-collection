<?php
namespace TYPO3\Neos\Service;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Neos".            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeType;

/**
 * Renders the Node Type Schema in a format the User Interface understands; additionally pre-calculating node constraints
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeSchemaBuilder
{
    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Domain\Service\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
    * @Flow\InjectConfiguration(path="userInterface.aloha.presets", package="TYPO3.Neos")
    * @var array
    */
    protected $alohaPresets;

    /**
     * The preprocessed node type schema contains everything we need for the UI:
     *
     * - "nodeTypes" contains the original (merged) node type schema
     * - "inheritanceMap.subTypes" contains for every parent type the transitive list of subtypes
     * - "constraints" contains for each node type, the list of allowed child node types; normalizing
     *   whitelists and blacklists:
     *   - [node type]
     *     - nodeTypes:
     *       [child node type name]: TRUE
     *     - childNodes:
     *       - [child node name]
     *         - nodeTypes:
     *          [child node type name]: TRUE
     *
     * @return array the node type schema ready to be used by the JavaScript code
     */
    public function generateNodeTypeSchema()
    {
        $schema = array(
            'inheritanceMap' => array(
                'subTypes' => array()
            ),
            'nodeTypes' => array(),
            'constraints' => $this->generateConstraints()
        );

        $nodeTypes = $this->nodeTypeManager->getNodeTypes(true);
        /** @var NodeType $nodeType */
        foreach ($nodeTypes as $nodeTypeName => $nodeType) {
            if ($nodeType->isAbstract() === false) {
                $configuration = $nodeType->getFullConfiguration();
                $this->loadAlohaPreset($configuration);
                $this->flattenAlohaFormatOptions($configuration);
                $schema['nodeTypes'][$nodeTypeName] = $configuration;
                $schema['nodeTypes'][$nodeTypeName]['label'] = $nodeType->getLabel();
            }

            $schema['inheritanceMap']['subTypes'][$nodeTypeName] = array();
            foreach ($this->nodeTypeManager->getSubNodeTypes($nodeType->getName(), true) as $subNodeType) {
                /** @var NodeType $subNodeType */
                $schema['inheritanceMap']['subTypes'][$nodeTypeName][] = $subNodeType->getName();
            }
        }

        return $schema;
    }

    /**
     * @param array $options
     * @return array
     * @throws \Exception
     */
    protected function loadAlohaPreset(array &$options)
    {
        if (array_key_exists('properties', $options)) {
            foreach ($options['properties'] as &$property) {
                if (array_key_exists('ui', $property)) {
                    if (array_key_exists('aloha', $property['ui'])) {
                        if (array_key_exists('preset', $property['ui']['aloha'])) {
                            try {
                                $preset = $this->alohaPresets[$property['ui']['aloha']['preset']];
                                $originalAlohaConfiguration = $property['ui']['aloha'];
                                // Merge preset first so a single preset setting can always be overwritten by custom config
                                $property['ui']['aloha'] = array_merge($preset, $originalAlohaConfiguration);
                            } catch (\Exception $exception) {
                                throw new \Exception('Preset' . $property['ui']['aloha']['preset'] . '  does not exist', 123917897712);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * In order to allow unsetting options via the YAML settings merging, the
     * formatting options can be set via 'option': TRUE, however, the frontend
     * schema expects a flattened plain numeric array. This methods adjust the setting
     * accordingly.
     *
     * @param array $options The options array, passed by reference
     * @return void
     */
    protected function flattenAlohaFormatOptions(array &$options)
    {
        if (isset($options['properties'])) {
            foreach (array_keys($options['properties']) as $propertyName) {
                if (isset($options['properties'][$propertyName]['ui']['aloha'])) {
                    foreach ($options['properties'][$propertyName]['ui']['aloha'] as $formatGroup => $settings) {
                        if (!is_array($settings) || in_array($formatGroup, array('formatlesspaste'))) {
                            continue;
                        }
                        $flattenedSettings = array();
                        foreach ($settings as $key => $option) {
                            if (is_numeric($key) && is_string($option)) {
                                $flattenedSettings[] = $option;
                            } elseif ($option === true) {
                                $flattenedSettings[] = $key;
                            }
                        }
                        $options['properties'][$propertyName]['ui']['aloha'][$formatGroup] = $flattenedSettings;
                    }
                }
            }
        }
    }

    /**
     * Generate the list of allowed sub-node-types per parent-node-type and child-node-name.
     *
     * @return array constraints
     */
    protected function generateConstraints()
    {
        $constraints = array();
        $nodeTypes = $this->nodeTypeManager->getNodeTypes(true);
        /** @var NodeType $nodeType */
        foreach ($nodeTypes as $nodeTypeName => $nodeType) {
            $constraints[$nodeTypeName] = array(
                'nodeTypes' => array(),
                'childNodes' => array()
            );
            foreach ($nodeTypes as $innerNodeTypeName => $innerNodeType) {
                if ($nodeType->allowsChildNodeType($innerNodeType)) {
                    $constraints[$nodeTypeName]['nodeTypes'][$innerNodeTypeName] = true;
                }
            }

            foreach ($nodeType->getAutoCreatedChildNodes() as $key => $_x) {
                foreach ($nodeTypes as $innerNodeTypeName => $innerNodeType) {
                    if ($nodeType->allowsGrandchildNodeType($key, $innerNodeType)) {
                        $constraints[$nodeTypeName]['childNodes'][$key]['nodeTypes'][$innerNodeTypeName] = true;
                    }
                }
            }
        }

        return $constraints;
    }
}
