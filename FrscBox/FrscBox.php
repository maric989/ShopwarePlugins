<?php

namespace FrscBox;

use Shopware\Bundle\AttributeBundle\Service\TypeMapping;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FrscBox extends Plugin
{

    public static function getSubscribedEvents(){
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Index' =>
                'onPostDispatch'
        ];
    }

    public function onPostDispatch(\Enlight_Event_EventArgs $args)
    {
        $this->container->get('Template')->addTemplateDir(
            $this->getPath() . '/Resources/Views/'
        );
    }

    public function build(ContainerBuilder $container)
    {
        $container->setParameter('frsc_box.plugin_dir', $this->getPath());
        parent::build($container);
    }
    /**
     * @param InstallContext $installContext
     */
    public function install(InstallContext $installContext)
    {
        $this->createAtribute();

    }

    /**
     * @param UninstallContext $uninstallContext
     */
    public function uninstall(UninstallContext $uninstallContext)
    {

    }

    private function createAtribute()
    {
        $service = $this->container->get('shopware_attribute.crud_service');

        $service->update('s_articles_attributes', 'package_id', TypeMapping::TYPE_COMBOBOX, [
            'label' => 'Package id',
            'supportText' => 'Bei gefrorenen Atikeln den Frost-Faktor angeben. 1 entspricht den Wert von gefrorenem Wasser.',
            'arrayStore' => [
                ['key' => '50', 'value' => 'Barf 100gr'],
                ['key' => '15.625', 'value' => 'Scheibe 250g '],
                ['key' => '29.41', 'value' => 'Scheibe 500g '],
                ['key' => '50', 'value' => 'Scheibe 1kg '],
                ['key' => '100', 'value'   => 'Fertigbarf 1kg'],
                ['key' => '200', 'value' => 'Fertigbarf 2.5kg']
            ],
            // configurable per subshop
            'translatable' => false,
            // do show this in the article module
            'displayInBackend' => true
        ]);
        // Rebuild attribute models
        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();

//        Shopware()->Models()->generateAttributeModels(
//            array('s_articles_attributes')
//        );
    }
}