<?php

namespace AppBundle\Controller;

use AppBundle\Entity\File;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $fileRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:File');
        /**@var File[] $files **/
        $files = $fileRepository->findAll();
        $formats = $this->extractField('format', $files);
        $data = [];
        $formatsAndIds = [];
        foreach ($files as $file) {
            $data[] = [
                'name' => $file->getName(),
                'format' => $file->getFormat(),
                'size' =>$file->getSize(),
            ];
            $formatsAndIds[] = [
                'name' => $file->getName(),
                'format' => $file->getFormat()
            ];
        }
        return $this->render('default/index.html.twig', ['data' => $data , 'formats' => $formats, 'formatsAndIds' => $formatsAndIds]);
    }

    /**
     * @Route("/add-file", name="loadFile")
     */
    public function loadFileAction(Request $request)
    {
        return $this->render('default/load.html.twig');
    }

    /**
     * @Route("/upload-file", name="uploadFile")
     */
    public function uploadFileAction(Request $request) {
        $uploadFile = '/var/www/upl/' . basename($_FILES[0]['name']);
        preg_match('#\.\w+$#', $_FILES[0]['name'], $format);
        move_uploaded_file($_FILES[0]['tmp_name'], $uploadFile);
        $File = new File();
        $File->setCreationDate(new \DateTime())
            ->setData(file_get_contents($uploadFile))
            ->setFormat(str_replace('.', '', $format[0]))
            ->setIsDelited(0)
            ->setName($_FILES[0]['name'])
            ->setPath($uploadFile)
            ->setSize($_FILES[0]['size']);
        $em = $this->getDoctrine()->getManager();
        $em->persist($File);
        $em->flush();
        return new JsonResponse(['status' => 'ok']);
    }


    /**
     * @param mixed $data
     * @param array $keys
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function arrayKeyExistRecursive($data, array $keys) {
        if (count($keys) === 0) {
            throw new \InvalidArgumentException('Keys must be a non-empty array');
        }
        /** @var mixed $tempData */
        $tempData = $data;
        foreach ($keys as $key) {
            if (is_array($tempData) && array_key_exists($key, $tempData)) {
                $tempData = $tempData[$key];
            } elseif (is_object($tempData) && array_key_exists($key, get_object_vars($tempData))) {
                $tempData = $tempData->$key;
            } elseif (is_object($tempData) && method_exists($tempData, $key)) {
                $tempData = $tempData->$key();
            } else {
                $method = 'get' . $key;
                return $tempData->$method();
            }
        }
        return true;
    }

    /**
     * @param mixed $data
     * @param array $keys
     * @param mixed $default
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function arrayGetRecursive($data, array $keys, $default = null) {
        if (!$this->arrayKeyExistRecursive($data, $keys)) {
            return $default;
        }

        /** @var mixed $tempData */
        $tempData = $data;
        foreach ($keys as $key) {
            if (is_object($tempData) && array_key_exists($key, get_object_vars($tempData))) {
                $tempData = $tempData->$key;
            } elseif (is_object($tempData) && method_exists($tempData, $key)) {
                $tempData = $tempData->$key();
            } elseif (is_array($tempData)) {
                $tempData = $tempData[$key];
            } else {
                $method = 'get' . $key;
                return $tempData->$method();
                return $default;
            }
        }
        return $tempData;
    }


    /**
     * @param mixed $object
     * @param string $fieldName
     * @return mixed
     */
    private function _getObjectFieldValue($object, $fieldName) {
        $isArrayKeyExistAndScalar        = is_array($object) && array_key_exists($fieldName, $object) && is_scalar($object[$fieldName]);
        $isObjectKeyExistAndScalar       = is_object($object) && array_key_exists($fieldName, get_object_vars($object)) && is_scalar($object->$fieldName);
        $isObjectMethodExistAndScalar    = is_object($object) && method_exists($object, $fieldName) && is_scalar($object->$fieldName());
        $getMethodName                   = 'get' . ucfirst($fieldName);
        $isObjectGetMethodExistAndScalar = is_object($object) && method_exists($object, $getMethodName) && is_scalar($object->$getMethodName());

        $value = null;
        if ($isObjectKeyExistAndScalar) {
            $value = $object->$fieldName;
        }

        if ($isArrayKeyExistAndScalar) {
            $value = $object[$fieldName];
        }

        if ($isObjectMethodExistAndScalar) {
            $value = $object->$fieldName();
        }

        if ($isObjectGetMethodExistAndScalar) {
            $value = $object->$getMethodName();
        }

        return $value;
    }

    /**
     * @param array $items
     * @param string $keyField
     * @param string $valueField
     * @return array
     *
     * Example class Foo has fields id, type and title
     * $items is a list of Foo
     * $keyField = "id",
     * $valueField = "title"
     * result will be [Foo1->id => Foo1->title, Foo2->id => Foo2->title, ...]
     */
    public function buildValuesMap(array $items, $keyField, $valueField) {
        $result = [];
        foreach ($items as $key => $item) {
            $key = $this->_getObjectFieldValue($item, $keyField);
            if ($key === null) {
                continue;
            }

            $result[$key] = $this->_getObjectFieldValue($item, $valueField);
        }
        return $result;
    }

    /**
     * @param string $fieldName
     * @param array $objects
     * @param bool $preserveKey
     * @return array
     */
    public function extractField($fieldName, array $objects, $preserveKey = false) {
        $result = [];
        foreach ($objects as $key => $object) {
            $value = $this->arrayGetRecursive($object, [$fieldName]);
            if ($preserveKey) {
                $result[$key] = $value;
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }
}
