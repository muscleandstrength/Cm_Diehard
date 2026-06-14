<?php
/**
 * List available javascript libraries
 *
 * @author Colin Mollenhour
 */
class Cm_Diehard_Model_System_Config_Jslib {

    public function toOptionArray()
    {
        $options = [];
        foreach( Mage::getConfig()->getNode('global/diehard/jslibs')->children() as $data) {
            $options[] = ['value' => (string) $data->path, 'label' => (string) $data->label];
        }
        return $options;
    }

}
