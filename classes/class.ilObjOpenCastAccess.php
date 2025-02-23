<?php
use srag\DIC\OpenCast\DICTrait;
/**
 * Access/Condition checking for OpenCast object
 *
 * @author        Fabian Schmid <fs@studer-raimann.ch>
 *
 * @version       1.0.00
 */
class ilObjOpenCastAccess extends ilObjectPluginAccess {

	use DICTrait;
	const PLUGIN_CLASS_NAME = ilOpenCastPlugin::class;

	const ROLE_MEMBER = 1;
	const ROLE_ADMIN = 2;
	const ROLE_TUTOR = 3;
	const TXT_PERMISSION_DENIED = 'permission_denied';

	const ACTION_EDIT_OWNER = 'edit_owner';
	const ACTION_SHARE_EVENT = 'share_event';
	const ACTION_CUT = 'cut';
	const ACTION_DELETE_EVENT = 'delete_event';
	const ACTION_EDIT_EVENT = 'edit_event';
	const ACTION_SET_ONLINE_OFFLINE = 'set_online_offline';
	const ACTION_ADD_EVENT = 'add_event';
	const ACTION_MANAGE_IVT_GROUPS = 'manage_ivt_groups';
	const ACTION_EDIT_SETTINGS = 'edit_settings';
	const ACTION_EXPORT_CSV = 'export_csv';
	const ACTION_REPORT_QUALITY_PROBLEM = 'report_quality_problem';
	const ACTION_REPORT_DATE_CHANGE = 'report_date_change';

	const PERMISSION_EDIT_VIDEOS = 'edit_videos';
	const PERMISSION_UPLOAD = 'upload';

	/**
	 * @var array
	 */
	protected static $custom_rights = array(
		self::PERMISSION_UPLOAD,
		self::PERMISSION_EDIT_VIDEOS,
	);
	/**
	 * @var array
	 */
	protected static $cache = array();
	/**
	 * @var array
	 */
	protected static $members = array();
	/**
	 * @var array
	 */
	protected static $tutors = array();
	/**
	 * @var array
	 */
	protected static $admins = array();


	/**
	 * @param string $a_cmd
	 * @param string $a_permission
	 * @param int $a_ref_id
	 * @param int $a_obj_id
	 * @param string $a_user_id
	 *
	 * @return bool
	 */
	public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id = NULL, $a_user_id = '') {
		if ($a_user_id == '') {
			$a_user_id = self::dic()->user()->getId();
		}
		if ($a_obj_id === NULL) {
			$a_obj_id = ilObject2::_lookupObjId($a_ref_id);
		}

		switch ($a_permission) {
			case 'read':
				if (!ilObjOpenCastAccess::checkOnline($a_obj_id) AND !self::dic()->access()->checkAccessOfUser($a_user_id, 'write', '', $a_ref_id)) {
					return false;
				}
				break;
			case 'visible':
				if (!ilObjOpenCastAccess::checkOnline($a_obj_id) AND !self::dic()->access()->checkAccessOfUser($a_user_id, 'write', '', $a_ref_id)) {
					return false;
				}
				break;
		}

		return true;
	}


	protected static function redirectNonAccess() {
		ilUtil::sendFailure(ilOpenCastPlugin::getInstance()->txt(self::TXT_PERMISSION_DENIED), true);
		self::dic()->ctrl()->redirectByClass('ilRepositoryGUI');
	}


	/**
	 * @param $a_id
	 *
	 * @return bool
	 */
	static function checkOnline($a_id) {
		require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/Series/class.xoctOpenCast.php');
		/**
		 * @var $xoctOpenCast xoctOpenCast
		 */
		$xoctOpenCast = xoctOpenCast::findOrGetInstance($a_id);

		return (bool)$xoctOpenCast->isOnline();
	}


	/**
	 * @param $ref_id
	 *
	 * @return bool
	 */
	public static function hasWriteAccess($ref_id = NULL) {
		if ($ref_id === NULL) {
			$ref_id = $_GET['ref_id'];
		}

		return self::dic()->access()->checkAccess('write', '', $ref_id);
	}


	/**
	 * @param                   $cmd
	 * @param xoctEvent|NULL    $xoctEvent
	 * @param xoctUser|NULL     $xoctUser
	 * @param xoctOpenCast|NULL $xoctOpenCast
	 * @param null              $ref_id
	 *
	 * @return bool
	 */
	public static function checkAction($cmd, xoctEvent $xoctEvent = NULL, xoctUser $xoctUser = NULL, xoctOpenCast $xoctOpenCast = NULL, $ref_id = NULL) {
		if ($xoctUser === NULL) {
			$xoctUser = xoctUser::getInstance(self::dic()->user());
		}

		if ($ref_id === NULL) {
			$ref_id = $_GET['ref_id'];
		}

		switch ($cmd) {
			case self::ACTION_EDIT_OWNER:
				return
					self::hasPermission(self::PERMISSION_EDIT_VIDEOS, $ref_id)
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_ENCODING
					&& ilObjOpenCast::_getParentCourseOrGroup($ref_id)
					&& $xoctOpenCast->getPermissionPerClip();
			case self::ACTION_SHARE_EVENT:
				return
					(self::hasPermission(self::PERMISSION_EDIT_VIDEOS, $ref_id) && $xoctOpenCast->getPermissionPerClip())
					|| ($xoctEvent->isOwner($xoctUser)
						&& $xoctOpenCast->getPermissionAllowSetOwn()
						&& $xoctEvent->getProcessingState() != xoctEvent::STATE_ENCODING
						&& $xoctEvent->getProcessingState() != xoctEvent::STATE_FAILED);
			case self::ACTION_CUT:
				return
					self::hasPermission(self::PERMISSION_EDIT_VIDEOS, $ref_id)
					&& $xoctEvent->hasPreviews()
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_FAILED;
			case self::ACTION_DELETE_EVENT:
				return
					(self::hasPermission(self::PERMISSION_EDIT_VIDEOS) || (self::hasPermission(self::PERMISSION_UPLOAD) && $xoctEvent->isOwner($xoctUser)))
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_ENCODING;
			case self::ACTION_EDIT_EVENT:
				return
					(self::hasPermission(self::PERMISSION_EDIT_VIDEOS) || (self::hasPermission(self::PERMISSION_UPLOAD) && $xoctEvent->isOwner($xoctUser)))
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_ENCODING
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_FAILED
					&& (!$xoctEvent->isScheduled() || xoctConf::getConfig(xoctConf::F_SCHEDULED_METADATA_EDITABLE) != xoctConf::NO_METADATA);
			case self::ACTION_SET_ONLINE_OFFLINE:
				return
					self::hasPermission(self::PERMISSION_EDIT_VIDEOS)
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_ENCODING
					&& $xoctEvent->getProcessingState() != xoctEvent::STATE_FAILED;
			case self::ACTION_ADD_EVENT:
				return
					self::hasPermission(self::PERMISSION_UPLOAD)
					|| self::hasPermission(self::PERMISSION_EDIT_VIDEOS);
			case self::ACTION_MANAGE_IVT_GROUPS:
				return
					self::hasPermission(self::PERMISSION_EDIT_VIDEOS);
			case self::ACTION_EDIT_SETTINGS:
				return
					self::hasWriteAccess(); // = permission: 'edit settings'
			case self::ACTION_EXPORT_CSV:
				return
					self::hasPermission(self::PERMISSION_EDIT_VIDEOS);
			case self::ACTION_REPORT_QUALITY_PROBLEM:
				return
					xoctConf::getConfig(xoctConf::F_REPORT_QUALITY)
					&& ((xoctConf::getConfig(xoctConf::F_REPORT_QUALITY_ACCESS) == xoctConf::ACCESS_ALL) || self::hasPermission(self::PERMISSION_EDIT_VIDEOS) || $xoctEvent->isOwner($xoctUser));
			case self::ACTION_REPORT_DATE_CHANGE:
				return
					xoctConf::getConfig(xoctConf::F_REPORT_DATE) && self::hasPermission(self::PERMISSION_EDIT_VIDEOS);
		}
	}



	/**
	 * @param      $right
	 * @param null $ref_id
	 *
	 * @return bool
	 */
	public static function hasPermission($right, $ref_id = NULL, $usr_id = 0) {
		if ($ref_id === NULL) {
			$ref_id = $_GET['ref_id'];
		}

		$prefix = in_array($right, self::$custom_rights) ? "rep_robj_xoct_perm_" : "";
		if ($usr_id == 0) {
			return self::dic()->access()->checkAccess($prefix.$right, '', $ref_id);
		}
		return self::dic()->access()->checkAccessOfUser($usr_id, $prefix.$right, '', $ref_id);
	}

	/**
	 * @param xoctUser $xoctUser
	 *
	 * @return bool
	 * @throws xoctException
	 */
	public static function hasReadAccessOnEvent(xoctEvent $xoctEvent, xoctUser $xoctUser, xoctOpenCast $xoctOpenCast) {
		// edit_videos and write access see all videos
		if (ilObjOpenCastAccess::hasPermission(self::PERMISSION_EDIT_VIDEOS) || ilObjOpenCastAccess::hasWriteAccess()) {
			return true;
		}

		// owner can see failed videos
		if ($xoctEvent->getProcessingState() == $xoctEvent::STATE_FAILED) {
			if ($xoctEvent->isOwner($xoctUser) && ($xoctOpenCast->getPermissionPerClip() || self::hasPermission(self::PERMISSION_UPLOAD))) {
				return true;
			}
			return false;
		}

		// don't show offline and failed videos
		if (!$xoctEvent->getXoctEventAdditions()->getIsOnline()) {
			return false;
		}

		// if owner, show video
		if ($xoctEvent->isOwner($xoctUser)) {
			return true;
		}

		// if not owner or edit_videos, don't show proceeding videos
		if (!($xoctEvent->getProcessingState() == xoctEvent::STATE_SUCCEEDED)) {
			return false;
		}

		// no ivt mode: show residual videos
		if (!$xoctOpenCast->getPermissionPerClip()) {
			return true;
		}

		// with ivt mode: show videos of ivt group and invitations (own videos already checked)
		$role_names = array();
		$xoctEvent->afterObjectLoad();

		$xoctGroupParticipants = xoctIVTGroup::getAllGroupParticipantsOfUser($xoctEvent->getSeriesIdentifier(), $xoctUser);
		foreach ($xoctGroupParticipants as $xoctGroupParticipant) {
			$role_names[] = $xoctGroupParticipant->getXoctUser()->getOwnerRoleName();
		}

		if ($xoctEvent->getOwnerAcl() instanceof xoctAcl && in_array($xoctEvent->getOwnerAcl()->getRole(), $role_names)) {
			return true; // same group as owner
		}

		$invitations = xoctInvitation::getAllInvitationsOfUser($xoctEvent->getIdentifier(), $xoctUser, $xoctOpenCast->getPermissionAllowSetOwn());
		if (!empty($invitations)) {
			return true; //has invitations
		}

		return false;
	}


	protected static function initRoleMembers() {
		static $init;
		if ($init) {
			return true;
		}

		$crs_or_grp_obj = ilObjOpenCast::_getParentCourseOrGroup($_GET['ref_id']);
		$roles = ($crs_or_grp_obj instanceof ilObjCourse) ? array('admin', 'tutor', 'member') : array('admin', 'member');
		foreach ($roles as $role) {
			$getter_method = "getDefault{$role}Role";
			$role_id = $crs_or_grp_obj->$getter_method();
			$participants = self::dic()->rbacreview()->assignedUsers($role_id);
			$setter_method = "set{$role}s";
			self::$setter_method($participants);
		}

		$init = true;
	}


	/**
	 * @param $action
	 * @param $role String member|tutor|admin
	 *
	 * @return bool
	 */
	public static function isActionAllowedForRole($action, $role, $ref_id = 0) {
        $ref_id = $ref_id ? $ref_id : $_GET['ref_id'];
		$prefix = in_array($action, self::$custom_rights) ? "rep_robj_xoct_perm_" : "";
		if (!$parent_obj = ilObjOpenCast::_getParentCourseOrGroup($ref_id)) {
			return false;
		}
		$fetch_role_method = "getDefault{$role}Role";
		$active_operations = self::dic()->rbacreview()->getActiveOperationsOfRole($ref_id, $parent_obj->$fetch_role_method());
		foreach ($active_operations as $op_id) {
			$operation = self::dic()->rbacreview()->getOperation($op_id);
			if ($operation['operation'] ==  $prefix.$action) {
				return true;
			}
		}
		return false;
	}


	/**
	 * returns array of xoctUsers who have the permission 'edit_videos' in this context
	 *
	 * @param $ref_id
	 *
	 * @return xoctUser[]
	 */
	public static function getProducersForRefID($ref_id) {
		$producers = [];
		if ($crs_or_grp_obj = ilObjOpenCast::_getParentCourseOrGroup($ref_id)) {
			//check each role (admin,tutor,member) for perm edit_videos, add to producers
			$roles = ($crs_or_grp_obj instanceof ilObjCourse) ? array('admin', 'tutor', 'member') : array('admin', 'member');
			foreach ($roles as $role) {
				if (self::isActionAllowedForRole(self::PERMISSION_EDIT_VIDEOS, $role, $ref_id)) {
					$getter_method = "getDefault{$role}Role";
					$role_id = $crs_or_grp_obj->$getter_method();
					foreach (self::dic()->rbacreview()->assignedUsers($role_id) as $participant_id) {
						$producers[] = xoctUser::getInstance($participant_id);
					}
				}
			}
		}
		return $producers;
	}


	/**
	 * used at object creation
	 *
	 * @param $ref_id
	 * @param $parent_ref_id
	 */
	public static function activateMemberUpload($ref_id) {
		$parent_obj = ilObjOpenCast::_getParentCourseOrGroup($ref_id);
		$member_role_id = $parent_obj->getDefaultMemberRole();
		$ops_id_upload = self::dic()->rbacreview()->_getOperationIdByName('rep_robj_xoct_perm_upload');
		$ops_ids = self::dic()->rbacreview()->getActiveOperationsOfRole($ref_id, $member_role_id);
		$ops_ids[] = $ops_id_upload;
		self::dic()->rbacadmin()->grantPermission($member_role_id, $ops_ids, $ref_id);
	}


	/**
	 * @return int
	 */
	public static function getParentId($get_ref_id = false, $ref_id = false) {
		foreach (self::dic()->tree()->getNodePath($ref_id ? $ref_id : $_GET['ref_id']) as $node) {
			if ($node['type'] == 'crs' || $node['type'] == 'grp') {
				$id = $node[$get_ref_id ? 'child' : 'obj_id'];
			}
		}

		return $id;
	}


	/**
	 * @return array
	 */
	public static function getAllParticipants() {
		return array_merge(self::getMembers(), self::getTutors(), self::getAdmins());
	}

	/**
	 * @return array
	 */
	public static function getMembers() {
		self::initRoleMembers();

		return self::$members;
	}


	/**
	 * @param array $members
	 */
	public static function setMembers($members) {
		self::$members = $members;
	}


	/**
	 * @return array
	 */
	public static function getTutors() {
		self::initRoleMembers();

		return self::$tutors;
	}


	/**
	 * @param array $tutors
	 */
	public static function setTutors($tutors) {

		self::$tutors = $tutors;
	}


	/**
	 * @return array
	 */
	public static function getAdmins() {
		self::initRoleMembers();

		return self::$admins;
	}


	/**
	 * @param array $admins
	 */
	public static function setAdmins($admins) {
		self::$admins = $admins;
	}
}

?>
