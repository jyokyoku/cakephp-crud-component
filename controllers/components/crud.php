<?php
class CrudComponent extends Object
{
	public $Controller;
	public $Model;

	public function initialize($Controller, $config = array())
	{
		$this->Controller = $Controller;
	}

	/**
	 * データを作成する
	 *
	 * $options には下記を指定可能
	 * - model: モデル名またはモデルオブジェクト
	 * - saveMethod: saveもしくはsaveAll
	 * - validate: save の場合は true/false, saveAll の場合は first/only/false
	 *
	 * @param array $options
	 * @return mixed true/false, Controller::$data が空の場合は null が返る
	 */
	public function c($options = array())
	{
		if (!$Model = $this->_getModel($options)) {
			return null;
		}

		$result = null;

		if (!empty($this->Controller->data[$Model->alias])) {
			$Model->create();
			$result = $this->_save($Model, $options);
		}

		return $result;
	}

	/**
	 * データを読み込む
	 *
	 * $options には下記を指定可能
	 * - model: モデル名またはモデルオブジェクト
	 * - paginate: ページネーションを利用する
	 * - findType: 検索用メソッド
	 * - set: $Controller::viewVarsにセットする
	 * - varname: $Controller::viewVarsにセットする変数名
	 *     空の場合は利用モデル名(単数・複数)が使われる
	 *
	 * @param mixed $id モデルのIDまたはクエリ配列
	 * @param array $options
	 * @return array
	 */
	public function r($id = null, $options = array())
	{
		if (!$Model = $this->_getModel($options)) {
			return null;
		}

		$defaults = array('paginate' => true, 'findType' => 'all', 'set' => true, 'varname' => '');
		$options = array_merge($defaults, $options);

		if (empty($id)) {
			$id = array();
		}

		if (is_array($id)) {
			$var = ($options['varname']) ? $options['varname'] : Inflector::variable(Inflector::pluralize($Model->alias));

			if ($options['paginate']) {
				if (isset($id['conditions'])) {
					$id = $id['conditions'];
				}

				$$var = $this->Controller->paginate($Model->alias, $id);

			} else {
				$$var = $Model->find($options['findType'], $id);
			}

		} else {
			$var = ($options['varname']) ? $options['varname'] : Inflector::variable($Model->alias);
			$$var = $Model->read(null, $id);
		}

		if ($options['set']) {
			$this->Controller->set(compact($var));
		}

		return $$var;
	}

	/**
	 * データを更新する
	 *
	 * $options には下記を指定する
	 * - model: モデル名またはモデルオブジェクト
	 * - saveMethod: saveもしくはsaveAll
	 * - validate: save の場合は true/false, saveAll の場合は first/only/false
	 *
	 * @param mixed $id 更新するモデルのIDまたは検索条件
	 * @param array $options
	 */
	public function u($id = null, $options = array())
	{
		if (!($Model = $this->_getModel($options))) {
			return null;
		}

		$defaults = array('set' => true, 'varName' => '');
		$options = array_merge($defaults, $options);

		$var = (!$options['varName'] || !preg_match('/^[^\d][0-9a-zA-Z_]+$/', (string)$options['varName']))
			 ? Inflector::variable($Model->alias)
			 : (string)$options['varName'];
		${$var} = is_array($id) ? $Model->find('first', array('conditions' => $id)) : $Model->findById($id);

		if (!${$var}) {
			return null;
		}

		$result = null;

		if (!empty($this->Controller->data[$Model->alias])) {
			$this->Controller->data[$Model->alias][$Model->primaryKey] = ${$var}[$Model->alias][$Model->primaryKey];
			$result = $this->_save($Model, $options);

		} else {
			$this->Controller->data = ${$var};
		}

		if ($options['set']) {
			$this->Controller->set(compact($var));
		}

		return $result;
	}

	/**
	 * データを削除する
	 *
	 * $options は下記を指定する
	 * - model: モデル名またはモデルオブジェクト
	 * - scope: 検索条件
	 * - fields: 削除成功したモデルの配列に含めるフィールド名
	 * - cascade: 関連モデルのデータも削除
	 * - callbacks: 削除のコールバックを利用
	 * - formField: 利用するフォームのフィールド名
	 * - formMethod: 利用するフォーム名
	 *
	 * @param int $id 削除するモデルのID
	 * @param array $options
	 */
	public function d($id, $options = array())
	{
		if (!$Model = $this->_getModel($options)) {
			return null;
		}

		$defaults = array(
			'scope' => array(), 'fields' => array(), 'cascade' => true, 'callbacks' => false,
			'formField' => 'id', 'formMethod' => 'post'
		);
		$options = array_merge($defaults, $options);

		if (empty($id)) {
			$form = ($options['formMethod'] == 'post') ? 'form' : 'url';

			if (isset($this->Controller->params[$form][$options['formField']])) {
				$id = $this->Controller->params[$form][$options['formField']];
			}
		}

		$result = null;

		if (!empty($id)) {
			if (!is_array($id)) {
				$id = array($id);
			}

			$conditions = array_merge((array) $options['scope'], array($Model->escapeField() => $id));
			$deleted = $Model->find('all', array('conditions' => $conditions, 'fields' => $options['fields']));

			if ($Model->deleteAll($conditions, $options['cascade'], $options['callbacks'])) {
				$result = $deleted;

			} else {
				$result = false;
			}
		}

		return $result;
	}

	protected function _save($Model, $options)
	{
		$defaults = array('saveMethod' => 'save', 'validate' => true);
		$options = array_merge($defaults, $options);

		$saveMethod = $options['saveMethod'];
		unset($options['saveMethod']);

		if ($saveMethod == 'save') {
			$options = (bool)$options['validate'];
		}

		return $Model->{$saveMethod}($this->Controller->data, $options);
	}

	protected function _getModel($options = array())
	{
		if (!empty($options['model'])) {
			if (is_object($options['model']) && is_a($options['model'], 'Model')) {
				return $options['model'];

			} else if (is_string($options['model']) && ($model = ClassRegistry::init($options['model']))) {
				return $model;
			}

		} else if (isset($this->Controller->{$this->Controller->modelClass})
			&& is_a($this->Controller->{$this->Controller->modelClass}, 'Model')) {
			return $this->Controller->{$this->Controller->modelClass};

		} else if (isset($this->Controller->modelNames[0]) && isset($this->Controller->{$this->Controller->modelNames[0]})
			&& is_a($this->Controller->{$this->Controller->modelNames[0]}, 'Model')) {
			return $this->Controller->{$this->Controller->modelNames[0]};
		}

		$message = 'Model is not set or could not be found';
		trigger_error($message, E_USER_WARNING);

		return null;
	}
}