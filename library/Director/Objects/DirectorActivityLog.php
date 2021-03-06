<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use Icinga\Authentication\Auth;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;

class DirectorActivityLog extends DbObject
{
    protected $table = 'director_activity_log';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'              => null,
        'object_name'     => null,
        'action_name'     => null,
        'object_type'     => null,
        'old_properties'  => null,
        'new_properties'  => null,
        'author'          => null,
        'change_time'     => null,
        'checksum'        => null,
        'parent_checksum' => null,
    );

    /**
     * @param $name
     *
     * @codingStandardsIgnoreStart
     *
     * @return self
     */
    protected function setObject_Name($name)
    {
        // @codingStandardsIgnoreEnd

        if ($name === null) {
            $name = '';
        }

        return $this->reallySet('object_name', $name);
    }

    protected static function username()
    {
        if (Icinga::app()->isCli()) {
            return 'cli';
        }

        $auth = Auth::getInstance();
        if ($auth->isAuthenticated()) {
            return $auth->getUser()->getUsername();
        } else {
            return '<unknown>';
        }
    }

    public static function loadLatest(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from('director_activity_log', array('id' => 'MAX(id)'));
        return static::load($db->fetchOne($query), $connection);
    }

    public static function logCreation(IcingaObject $object, Db $db)
    {
        // TODO: extend this to support non-IcingaObjects and multikey objects
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $newProps = $object->toJson(null, true);
        $data = array(
            'object_name'     => $name,
            'action_name'     => 'create',
            'author'          => self::username(),
            'object_type'     => $type,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        if ($db->settings()->enable_audit_log === 'y') {
            Logger::info('(director) %s[%s] has been created: %s', $type, $name, $newProps);
        }
        return self::create($data)->store($db);
    }

    public static function logModification(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $oldProps = json_encode($object->getPlainUnmodifiedObject());
        $newProps = $object->toJson(null, true);
        $data = array(
            'object_name'     => $name,
            'action_name'     => 'modify',
            'author'          => self::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        if ($db->settings()->enable_audit_log === 'y') {
            Logger::info('(director) %s[%s] has been modified from %s to %s', $type, $name, $oldProps, $newProps);
        }
        return self::create($data)->store($db);
    }

    public static function logRemoval(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $oldProps = json_encode($object->getPlainUnmodifiedObject());

        $data = array(
            'object_name'     => $name,
            'action_name'     => 'delete',
            'author'          => self::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        if ($db->settings()->enable_audit_log === 'y') {
            Logger::info('(director) %s[%s] has been removed: %s', $type, $name, $oldProps);
        }
        return self::create($data)->store($db);
    }
}
