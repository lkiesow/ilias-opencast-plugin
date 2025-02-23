<?php

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use srag\DIC\OpenCast\DICTrait;
use srag\DIC\OpenCast\Exception\DICException;

/**
 * Class xoctEventRenderer
 *
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class xoctEventRenderer {

	use DICTrait;
	const PLUGIN_CLASS_NAME = ilOpenCastPlugin::class;
	const LANG_MODULE = 'event';

	/**
	 * @var xoctEvent
	 */
	protected $xoctEvent;
	/**
	 * @var null | xoctOpenCast
	 */
	protected $xoctOpenCast;
	/**
	 * @var Factory
	 */
	protected $factory;
	/**
	 * @var Renderer
	 */
	protected $renderer;

	/**
	 * xoctEventRenderer constructor.
	 * @param $xoctEvent xoctEvent
	 * @param null $xoctOpenCast
	 */
	public function __construct($xoctEvent, $xoctOpenCast = null) {
		$this->xoctEvent = $xoctEvent;
		$this->xoctOpenCast = $xoctOpenCast;
		$this->factory = self::dic()->ui()->factory();
		$this->renderer = self::dic()->ui()->renderer();
	}

	/**
	 * @param $tpl ilTemplate
	 * @param $variable string
	 * @param $value string
	 * @param string $block_title string
	 */
	public function insert(&$tpl, $variable, $value, $block_title = '') {
		if ($block_title) {
			$tpl->setCurrentBlock($block_title);
		}

		$tpl->setVariable($variable, $value);

		if ($block_title) {
			$tpl->parseCurrentBlock();
		}
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 */
	public function insertThumbnail(&$tpl, $block_title = 'thumbnail', $variable = 'THUMBNAIL') {
		$this->insert($tpl, $variable, $this->getThumbnailHTML(), $block_title);
	}

	/**
	 * @return string
	 */
	public function getThumbnailHTML() {
		return $this->renderer->render($this->factory->image()->responsive($this->xoctEvent->getThumbnailUrl(), 'Thumbnail'));
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @param string $button_type
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function insertPlayerLink(&$tpl, $block_title = 'link', $variable = 'LINK', $button_type = 'btn-info') {
		if ($player_link_html = $this->getPlayerLinkHTML($button_type)) {
			$this->insert($tpl, $variable, $player_link_html, $block_title);
		}
	}

	/**
	 * @param string $button_type
	 * @return string
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function getPlayerLinkHTML($button_type = 'btn-info') {
		if (($this->xoctEvent->getProcessingState() == xoctEvent::STATE_SUCCEEDED) && ($player_link = $this->xoctEvent->getPlayerLink())) {
			$link_tpl = self::plugin()->template('default/tpl.player_link.html');
			$link_tpl->setVariable('LINK_TEXT', self::plugin()->translate('player', self::LANG_MODULE));
			$link_tpl->setVariable('BUTTON_TYPE', $button_type);
			if (xoctConf::getConfig(xoctConf::F_USE_MODALS)) {
				$modal = ilModalGUI::getInstance();
				$modal->setId('modal_' . $this->xoctEvent->getIdentifier());
				$modal->setHeading($this->xoctEvent->getTitle());
				$modal->setBody('<iframe class="xoct_iframe" src="' . $player_link . '"></iframe>');
				$link_tpl->setVariable('MODAL', $modal->getHTML());
				$link_tpl->setVariable('MODAL_LINK', 'data-toggle="modal" data-target="#modal_' . $this->xoctEvent->getIdentifier() . '"');
				$link_tpl->setVariable('LINK_URL', '#');
			} else {
				$link_tpl->setVariable('LINK_URL', $player_link);
			}

			return $link_tpl->get();
		} else {
			return '';
		}
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @param string $button_type
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function insertDownloadLink(&$tpl, $block_title = 'link', $variable = 'LINK', $button_type = 'btn-info') {
		if ($download_link_html = $this->getDownloadLinkHTML($button_type)) {
			$this->insert($tpl, $variable, $download_link_html, $block_title);
		}
	}

	/**
	 * @param string $button_type
	 * @return string
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function getDownloadLinkHTML($button_type = 'btn_info') {
		if (($this->xoctEvent->getProcessingState() == xoctEvent::STATE_SUCCEEDED) && ($download_link = $this->xoctEvent->getDownloadLink())) {
			if ($this->xoctOpenCast instanceof xoctOpenCast && $this->xoctOpenCast->getStreamingOnly()) {
				return '';
			}
			$link_tpl = self::plugin()->template('default/tpl.player_link.html');
			$link_tpl->setVariable('BUTTON_TYPE', $button_type);
			$link_tpl->setVariable('LINK_TEXT', self::plugin()->translate('download', self::LANG_MODULE));
			$link_tpl->setVariable('LINK_URL', $download_link);

			return $link_tpl->get();
		} else {
			return '';
		}
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @param string $button_type
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function insertAnnotationLink(&$tpl, $block_title = 'link', $variable = 'LINK', $button_type = 'btn-info') {
		if ($annotation_link_html = $this->getAnnotationLinkHTML($button_type)) {
			$this->insert($tpl, $variable, $annotation_link_html, $block_title);
		}
	}

	/**
	 * @param string $button_type
	 * @return string
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function getAnnotationLinkHTML($button_type = 'btn_info') {
		if (($this->xoctEvent->getProcessingState() == xoctEvent::STATE_SUCCEEDED) && ($this->xoctEvent->getAnnotationLink())) {
			if ($this->xoctOpenCast instanceof xoctOpenCast && !$this->xoctOpenCast->getUseAnnotations()) {
				return '';
			}

			$annotations_link = self::dic()->ctrl()->getLinkTargetByClass(xoctEventGUI::class, xoctEventGUI::CMD_ANNOTATE);
			$link_tpl = self::plugin()->template('default/tpl.player_link.html');
			$link_tpl->setVariable('BUTTON_TYPE', $button_type);
			$link_tpl->setVariable('LINK_TEXT', self::plugin()->translate('annotate', self::LANG_MODULE));
			$link_tpl->setVariable('LINK_URL', $annotations_link);

			return $link_tpl->get();
		} else {
			return '';
		}
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @throws DICException
	 * @throws ilTemplateException
	 * @throws xoctException
	 */
	public function insertTitle(&$tpl, $block_title = 'title', $variable = 'TITLE') {
		$this->insert($tpl, $variable, $this->getTitleHTML(), $block_title);
	}

	/**
	 * @return string
	 */
	public function getTitleHTML() {
		return $this->xoctEvent->getTitle();
	}

	/**
	 * @param $tpl
	 * @param string $block_title
	 * @param string $variable
	 * @throws DICException
	 * @throws ilTemplateException
	 * @throws xoctException
	 */
	public function insertState(&$tpl, $block_title = 'state', $variable = 'STATE') {
		if ($state_html = $this->getStateHTML()) {
			$this->insert($tpl, $variable, $state_html, $block_title);
		}
	}

	/**
	 * @return string
	 * @throws DICException
	 * @throws ilTemplateException
	 * @throws xoctException
	 */
	public function getStateHTML() {
		if ($this->xoctEvent->getProcessingState() != xoctEvent::STATE_SUCCEEDED) {
			$state_tpl = self::plugin()->template('default/tpl.event_state.html');
			$state_tpl->setVariable('STATE_CSS', xoctEvent::$state_mapping[$this->xoctEvent->getProcessingState()]);


			$owner = $this->xoctEvent->isOwner(xoctUser::getInstance(self::dic()->user()))
			&& in_array($this->xoctEvent->getProcessingState(), array(
				xoctEvent::STATE_FAILED,
				xoctEvent::STATE_ENCODING
			)) ? '_owner' : '';
			$state_tpl->setVariable('STATE', self::plugin()->translate('state_' . strtolower($this->xoctEvent->getProcessingState()) . $owner, self::LANG_MODULE));

			return $state_tpl->get();
		} else {
			return '';
		}
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 */
	public function insertPresenter(&$tpl, $block_title = 'presenter', $variable = 'PRESENTER') {
		$this->insert($tpl, $variable, $this->getPresenterHTML(), $block_title);
	}

	/**
	 * @return String
	 */
	public function getPresenterHTML() {
		return $this->xoctEvent->getPresenter();
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 */
	public function insertLocation(&$tpl, $block_title = 'location', $variable = 'LOCATION') {
		$this->insert($tpl, $variable, $this->getLocationHTML(), $block_title);
	}

	/**
	 * @return string
	 */
	public function getLocationHTML() {
		return $this->xoctEvent->getLocation();
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @param string $format
	 */
	public function insertStart(&$tpl, $block_title = 'start', $variable = 'START', $format = 'd.m.Y - H:i') {
		$this->insert($tpl, $variable, $this->getStartHTML($format), $block_title);
	}

	/**
	 * @param string $format
	 * @return string
	 */
	public function getStartHTML($format = 'd.m.Y - H:i') {
		return $this->xoctEvent->getStart()->format($format);
	}

	/**
	 * @param $tpl ilTemplate
	 * @param string $block_title
	 * @param string $variable
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function insertOwner(&$tpl, $block_title = 'owner', $variable = 'OWNER') {
		$this->insert($tpl, $variable, $this->getOwnerHTML(), $block_title);
	}

	/**
	 * @return string
	 * @throws DICException
	 * @throws ilTemplateException
	 */
	public function getOwnerHTML() {
		$owner_tpl = self::plugin()->template('default/tpl.event_owner.html');
		$owner_tpl->setVariable('OWNER', $this->xoctEvent->getOwnerUsername());

		if ($this->xoctOpenCast instanceof xoctOpenCast && $this->xoctOpenCast->getPermissionPerClip()) {
			$owner_tpl->setCurrentBlock('invitations');
			$in = xoctInvitation::getActiveInvitationsForEvent($this->xoctEvent, $this->xoctOpenCast, true);
			if ($in > 0) {
				$owner_tpl->setVariable('INVITATIONS', $in);
			}
			$owner_tpl->parseCurrentBlock();
		}

		return $owner_tpl->get();
	}


}