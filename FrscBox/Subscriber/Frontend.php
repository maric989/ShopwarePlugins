<?php
//
//namespace FrscBox\Subscriber;
//
//use Doctrine\Common\Collections\ArrayCollection;
//use Enlight\Event\SubscriberInterface;
//use Symfony\Component\DependencyInjection\ContainerInterface;
//
//class Frontend implements SubscriberInterface
//{
//    /**
//     * @var ContainerInterface
//     */
//    private $container;
//
//    /**
//     * @var string
//     */
//    private $pluginPath;
//
//    /**
//     * @param ContainerInterface $container
//     */
//    public function __construct(ContainerInterface $container, $pluginPath)
//    {
//        $this->container = $container;
//        $this->pluginPath = $pluginPath;
//    }
//
//    /**
//     * @return array
//     */
//    public static function getSubscribedEvents()
//    {
//        return array(
//            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
//        );
//    }
//
//    /**
//     * @param \Enlight_Event_EventArgs $args
//     */
//    public function onPreDispatch(\Enlight_Event_EventArgs $args)
//    {
//        $this->container->get('template')->addTemplateDir($this->pluginPath . '/Resources/views/');
//    }
//
//    public function addJsFiles()
//    {
//        $jsFiles = array(__DIR__ . '/Views/_public/src/js/myFile.js');
//
//        return new ArrayCollection($jsFiles);
//    }
//}
