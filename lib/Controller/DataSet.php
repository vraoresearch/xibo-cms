<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;
use baseDAO;
use DataSetColumn;
use DataSetData;
use DataSetGroupSecurity;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Config;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class DataSet extends Base
{
    public function displayPage()
    {
        $subpage = \Kit::GetParam('sp', _GET, _WORD, '');

        // Configure the theme
        $id = uniqid();

        // Different pages for data entry and admin
        if ($subpage == 'DataEntry') {
            Theme::Set('id', 'DataEntryGrid');
            $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
            $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

            Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetDataForm"><input type="hidden" name="datasetid" value="' . $dataSetId . '"><input type="hidden" name="dataset" value="' . $dataSet . '">');

            // Call to render the template
            Theme::Set('header_text', $dataSet);
            Theme::Set('form_fields', array());
            $this->getState()->html .= Theme::RenderReturn('grid_render');
        } else {
            $id = uniqid();
            Theme::Set('id', $id);
            Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetGrid">');
            Theme::Set('pager', ApplicationState::Pager($id));

            // Call to render the template
            Theme::Set('header_text', __('DataSets'));
            Theme::Set('form_fields', array());
            $this->getState()->html .= Theme::RenderReturn('grid_render');
        }
    }

    function actionMenu()
    {

        if (\Kit::GetParam('sp', _GET, _WORD, 'view') == 'view') {
            return array(
                array('title' => __('Add DataSet'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=dataset&q=AddDataSetForm',
                    'help' => __('Add a new DataSet'),
                    'onclick' => ''
                )
            );
        } else if (\Kit::GetParam('sp', _GET, _WORD, 'view') == 'DataEntry') {
            return array(
                array('title' => __('More Rows'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Add more rows to the end of this DataSet'),
                    'onclick' => 'XiboGridRender(\'DataEntryGrid\')'
                )
            );
        } else
            return NULL;
    }

    public function DataSetGrid()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $cols = array(
            array('name' => 'dataset', 'title' => __('Name')),
            array('name' => 'description', 'title' => __('Description')),
            array('name' => 'owner', 'title' => __('Owner')),
            array('name' => 'groups', 'title' => __('Permissions'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($this->getUser()->DataSetList() as $dataSet) {
            // Add some additional info
            $dataSet['owner'] = $user->getNameFromID($dataSet['ownerid']);
            $dataSet['groups'] = $this->GroupsForDataSet($dataSet['datasetid']);
            $dataSet['buttons'] = array();

            if ($dataSet['edit']) {

                // View Data
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_viewdata',
                    'class' => 'XiboRedirectButton',
                    'url' => 'index.php?p=dataset&sp=DataEntry&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('View Data')
                );

                // View Columns
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_viewcolumns',
                    'url' => 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('View Columns')
                );

                // Edit DataSet
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_edit',
                    'url' => 'index.php?p=dataset&q=EditDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('Edit')
                );

                // Edit DataSet
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_import',
                    'url' => 'index.php?p=dataset&q=ImportCsvForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('Import CSV')
                );
            }

            if ($dataSet['del']) {

                // Delete DataSet
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_delete',
                    'url' => 'index.php?p=dataset&q=DeleteDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('Delete')
                );
            }

            if ($dataSet['modifyPermissions']) {

                // Edit Permissions
                $dataSet['buttons'][] = array(
                    'id' => 'dataset_button_delete',
                    'url' => 'index.php?p=dataset&q=PermissionsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                    'text' => __('Permissions')
                );
            }

            $rows[] = $dataSet;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);

    }

    public function AddDataSetForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        // Set some information about the form
        Theme::Set('form_id', 'AddDataSetForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=AddDataSet');

        $formFields = array();
        $formFields[] = Form::AddText('dataset', __('Name'), NULL,
            __('A name for this DataSet'), 'n', 'required');

        $formFields[] = Form::AddText('description', __('Description'), NULL,
            __('An optional description'), 'd', 'maxlength="250"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Add'), '$("#AddDataSetForm").submit()');

    }

    /**
     * Add a dataset
     */
    public function AddDataSet()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');
        $description = \Xibo\Helper\Sanitize::getString('description');

        $dataSetObject = new DataSet($db);
        if (!$dataSetId = $dataSetObject->Add($dataSet, $description, $this->getUser()->userId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        // Also add one column
        $dataSetColumn = new DataSetColumn($db);
        $dataSetColumn->Add($dataSetId, 'Col1', 1, null, 1);

        $response->SetFormSubmitResponse(__('DataSet Added'));

    }

    public function EditDataSetForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Get the information we already know
        $SQL = sprintf("SELECT DataSet, Description FROM dataset WHERE DataSetID = %d", $dataSetId);

        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unable to get DataSet information'));

        // Set some information about the form
        Theme::Set('form_id', 'EditDataSetForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=EditDataSet');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $formFields = array();
        $formFields[] = Form::AddText('dataset', __('Name'), \Xibo\Helper\Sanitize::string($row['DataSet']),
            __('A name for this DataSet'), 'n', 'required');

        $formFields[] = Form::AddText('description', __('Description'), \Xibo\Helper\Sanitize::string($row['Description']),
            __('An optional description'), 'd', 'maxlength="250"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#EditDataSetForm").submit()');

    }

    public function EditDataSet()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');
        $description = \Xibo\Helper\Sanitize::getString('description');

        $dataSetObject = new DataSet($db);
        if (!$dataSetObject->Edit($dataSetId, $dataSet, $description))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('DataSet Edited'));

    }

    /**
     * Return the Delete Form as HTML
     * @return
     */
    public function DeleteDataSetForm()
    {

        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetDeleteForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=DeleteDataSet');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $formFields = array();
        $formFields[] = Form::AddMessage(__('Are you sure you want to delete?'));
        $formFields[] = Form::AddCheckbox('deleteData', __('Delete any associated data?'), NULL,
            __('Please tick the box if you would like to delete all the Data contained in this DataSet'), 'c');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Delete this DataSet?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'Delete') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Delete'), '$("#DataSetDeleteForm").submit()');

    }

    public function DeleteDataSet()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        $dataSetObject = new DataSet($db);

        if ($dataSetObject->hasData($dataSetId) && \Kit::GetParam('deleteData', _POST, _CHECKBOX) == 0)
            trigger_error(__('There is data assigned to this data set, cannot delete.'), E_USER_ERROR);

        // Proceed with the delete
        if (!$dataSetObject->Delete($dataSetId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('DataSet Deleted'));

    }

    public function DataSetColumnsForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $SQL = "";
        $SQL .= "SELECT DataSetColumnID, Heading, datatype.DataType, datasetcolumntype.DataSetColumnType, ListContent, ColumnOrder ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= "   INNER JOIN `datatype` ";
        $SQL .= "   ON datatype.DataTypeID = datasetcolumn.DataTypeID ";
        $SQL .= "   INNER JOIN `datasetcolumntype` ";
        $SQL .= "   ON datasetcolumntype.DataSetColumnTypeID = datasetcolumn.DataSetColumnTypeID ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "ORDER BY ColumnOrder ";


        $dataSetColumnObject = new DataSetColumn($db);

        // Load results into an array
        if (!$dataSetColumns = $dataSetColumnObject->GetColumns($dataSetId))
            trigger_error($dataSetColumnObject->GetErrorMessage(), E_USER_ERROR);

        $rows = array();

        foreach ($dataSetColumns as $row) {

            $row['datatype'] = __($row['datatype']);
            $row['datasetcolumntype'] = __($row['datasetcolumntype']);

            // Edit
            $row['buttons'][] = array(
                'id' => 'dataset_button_edit',
                'url' => 'index.php?p=dataset&q=EditDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['datasetcolumnid'] . '&dataset=' . $dataSet,
                'text' => __('Edit')
            );

            if ($auth->del) {
                // Delete
                $row['buttons'][] = array(
                    'id' => 'dataset_button_delete',
                    'url' => 'index.php?p=dataset&q=DeleteDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['datasetcolumnid'] . '&dataset=' . $dataSet,
                    'text' => __('Delete')
                );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $form = Theme::RenderReturn('dataset_form_column_grid');

        $response->SetFormRequestResponse($form, sprintf(__('Columns for %s'), $dataSet), '550px', '400px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'ViewColumns') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Add Column'), 'XiboFormRender("index.php?p=dataset&q=AddDataSetColumnForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');

    }

    public function AddDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnAddForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=AddDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $formFields = array();
        $formFields[] = Form::AddText('heading', __('Heading'), NULL, __('The heading for this Column'), 'h', 'required');
        $formFields[] = Form::AddCombo(
            'datasetcolumntypeid',
            __('Column Type'),
            NULL,
            $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
            'datasetcolumntypeid',
            'datasetcolumntype',
            __('Whether this column is a value or a formula'),
            't');
        $formFields[] = Form::AddCombo(
            'datatypeid',
            __('Data Type'),
            NULL,
            $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
            'datatypeid',
            'datatype',
            __('The DataType of the Intended Data'),
            'd');
        $formFields[] = Form::AddText('listcontent', __('List Content'), NULL, __('A comma separated list of items to present in a combo box'), 'l', '');
        $formFields[] = Form::AddNumber('columnorder', __('Column Order'), NULL, __('The order this column should be displayed in when entering data'), 'o', '');
        $formFields[] = Form::AddText('formula', __('Formula'), NULL, __('A formula to use as a calculation for formula column types'), 'f', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'AddColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnAddForm").submit()');

    }

    public function AddDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $heading = \Xibo\Helper\Sanitize::getString('heading');
        $listContent = \Xibo\Helper\Sanitize::getString('listcontent');
        $columnOrder = \Xibo\Helper\Sanitize::getInt('columnorder');
        $dataTypeId = \Xibo\Helper\Sanitize::getInt('datatypeid');
        $dataSetColumnTypeId = \Xibo\Helper\Sanitize::getInt('datasetcolumntypeid');
        $formula = \Xibo\Helper\Sanitize::getString('formula');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Add($dataSetId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Added'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }

    public function EditDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnEditForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=EditDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" name="datasetcolumnid" value="' . $dataSetColumnId . '" />');

        // Get some information about this data set column
        $SQL = sprintf("SELECT Heading, ListContent, ColumnOrder, DataTypeID, DataSetColumnTypeID, Formula FROM datasetcolumn WHERE DataSetColumnID = %d", $dataSetColumnId);

        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unabled to get Data Column information'), E_USER_ERROR);

        // Dropdown list for DataType and DataColumnType
        Theme::Set('datatype_field_list', $db->GetArray('SELECT datatypeid, datatype FROM datatype'));
        Theme::Set('datasetcolumntype_field_list', $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'));

        $formFields = array();
        $formFields[] = Form::AddText('heading', __('Heading'), \Xibo\Helper\Sanitize::string($row['Heading']),
            __('The heading for this Column'), 'h', 'required');

        $formFields[] = Form::AddCombo(
            'datasetcolumntypeid',
            __('Column Type'),
            \Xibo\Helper\Sanitize::int($row['DataSetColumnTypeID']),
            $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
            'datasetcolumntypeid',
            'datasetcolumntype',
            __('Whether this column is a value or a formula'),
            't');

        $formFields[] = Form::AddCombo(
            'datatypeid',
            __('Data Type'),
            \Xibo\Helper\Sanitize::int($row['DataTypeID']),
            $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
            'datatypeid',
            'datatype',
            __('The DataType of the Intended Data'),
            'd');

        $formFields[] = Form::AddText('listcontent', __('List Content'), \Xibo\Helper\Sanitize::string($row['ListContent']),
            __('A comma separated list of items to present in a combo box'), 'l', '');

        $formFields[] = Form::AddNumber('columnorder', __('Column Order'), \Xibo\Helper\Sanitize::int($row['ColumnOrder']),
            __('The order this column should be displayed in when entering data'), 'o', '');

        $formFields[] = Form::AddText('formula', __('Formula'), \Xibo\Helper\Sanitize::string($row['Formula']),
            __('A formula to use as a calculation for formula column types'), 'f', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'EditColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnEditForm").submit()');

    }

    public function EditDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $heading = \Xibo\Helper\Sanitize::getString('heading');
        $listContent = \Xibo\Helper\Sanitize::getString('listcontent');
        $columnOrder = \Xibo\Helper\Sanitize::getInt('columnorder');
        $dataTypeId = \Xibo\Helper\Sanitize::getInt('datatypeid');
        $dataSetColumnTypeId = \Xibo\Helper\Sanitize::getInt('datasetcolumntypeid');
        $formula = \Xibo\Helper\Sanitize::getString('formula');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Edited'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }

    public function DeleteDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnDeleteForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=DeleteDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" name="datasetcolumnid" value="' . $dataSetColumnId . '" />');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(NULL, __('Delete this Column?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'DeleteColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Delete'), '$("#DataSetColumnDeleteForm").submit()');

    }

    public function DeleteDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Delete($dataSetColumnId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Deleted'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }

    public function DataSetDataForm()
    {

        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // Get the max number of rows
        $SQL = "";
        $SQL .= "SELECT MAX(RowNumber) AS RowNumber, COUNT(DISTINCT datasetcolumn.DataSetColumnID) AS ColNumber ";
        $SQL .= "  FROM datasetdata ";
        $SQL .= "   RIGHT OUTER JOIN datasetcolumn ";
        $SQL .= "   ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("WHERE datasetcolumn.DataSetID = %d  AND datasetcolumn.DataSetColumnTypeID = 1 ", $dataSetId);

        Log::notice($SQL, 'dataset', 'DataSetDataForm');

        if (!$maxResult = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to find the number of data points'), E_USER_ERROR);
        }

        $maxRows = $maxResult['RowNumber'];
        $maxCols = $maxResult['ColNumber'];

        // Get some information about the columns in this dataset
        $SQL = "SELECT Heading, DataSetColumnID, ListContent, ColumnOrder, DataTypeID FROM datasetcolumn WHERE DataSetID = %d  AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        if (!$results = $db->query(sprintf($SQL, $dataSetId))) {
            trigger_error($db->error());
            trigger_error(__('Unable to find the column headings'), E_USER_ERROR);
        }

        $columnDefinition = array();

        $form = '<table class="table table-bordered">';
        $form .= '   <tr>';
        $form .= '      <th>#</th>';

        while ($row = $db->get_assoc_row($results)) {
            $columnDefinition[] = $row;
            $heading = $row['Heading'];

            $form .= ' <th>' . $heading . '</th>';
        }

        $form .= '</tr>';

        // Loop through the max rows
        for ($row = 1; $row <= $maxRows + 2; $row++) {
            $form .= '<tr>';
            $form .= '  <td>' . $row . '</td>';

            // $row is the current row
            for ($col = 0; $col < $maxCols; $col++) {
                $dataSetColumnId = $columnDefinition[$col]['DataSetColumnID'];
                $listContent = $columnDefinition[$col]['ListContent'];
                $columnOrder = $columnDefinition[$col]['ColumnOrder'];
                $dataTypeId = $columnDefinition[$col]['DataTypeID'];

                // Value for this Col/Row
                $value = '';

                if ($row <= $maxRows) {
                    // This is intended to be a blank row
                    $SQL = "";
                    $SQL .= "SELECT Value ";
                    $SQL .= "  FROM datasetdata ";
                    $SQL .= "WHERE datasetdata.RowNumber = %d ";
                    $SQL .= "   AND datasetdata.DataSetColumnID = %d ";
                    $SQL = sprintf($SQL, $row, $dataSetColumnId);

                    Log::notice($SQL, 'dataset');

                    if (!$results = $db->query($SQL)) {
                        trigger_error($db->error());
                        trigger_error(__('Can not get the data row/column'), E_USER_ERROR);
                    }

                    if ($db->num_rows($results) == 0) {
                        $value = '';
                    } else {
                        $valueRow = $db->get_assoc_row($results);
                        $value = $valueRow['Value'];
                    }
                }

                // Do we need a select list?
                if ($listContent != '') {
                    $listItems = explode(',', $listContent);
                    $selected = ($value == '') ? ' selected' : '';
                    $select = '<select class="form-control" name="value">';
                    $select .= '     <option value="" ' . $selected . '></option>';

                    for ($i = 0; $i < count($listItems); $i++) {
                        $selected = ($listItems[$i] == $value) ? ' selected' : '';

                        $select .= '<option value="' . $listItems[$i] . '" ' . $selected . '>' . $listItems[$i] . '</option>';
                    }

                    $select .= '</select>';
                } else {
                    // Numbers are always small
                    $size = ($dataTypeId == 2) ? ' class="form-control col-sm-3"' : '';

                    if ($dataTypeId == 1) {
                        // Strings should be based on something - not sure what.
                    }

                    $select = '<input type="text" class="form-control ' . $size . '" name="value" value="' . $value . '">';
                }

                $action = ($value == '') ? 'AddDataSetData' : 'EditDataSetData';
                $fieldId = uniqid();

                $form .= <<<END
                <td>
                    <form id="$fieldId" class="XiboDataSetDataForm form-inline" action="index.php?p=dataset&q=$action">
                        <input type="hidden" name="fieldid" value="$fieldId">
                        <input type="hidden" name="datasetid" value="$dataSetId">
                        <input type="hidden" name="datasetcolumnid" value="$dataSetColumnId">
                        <input type="hidden" name="rownumber" value="$row">
                        $select
                    </form>
                </td>
END;
            } //cols loop

            $form .= '</tr>';
        } //rows loop

        $form .= '</table>';

        $response->SetGridResponse($form);
        $response->callBack = 'dataSetData';

    }

    public function AddDataSetData()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $response->uniqueReference = \Xibo\Helper\Sanitize::getString('fieldid');
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $rowNumber = \Xibo\Helper\Sanitize::getInt('rownumber');
        $value = \Xibo\Helper\Sanitize::getString('value');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetObject = new DataSetData($db);
        if (!$dataSetObject->Add($dataSetColumnId, $rowNumber, $value))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Data Added'));
        $response->loadFormUri = 'index.php?p=dataset&q=EditDataSetData';
        $response->hideMessage = true;
        $response->keepOpen = true;

    }

    public function EditDataSetData()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $response->uniqueReference = \Xibo\Helper\Sanitize::getString('fieldid');
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $rowNumber = \Xibo\Helper\Sanitize::getInt('rownumber');
        $value = \Xibo\Helper\Sanitize::getString('value');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        if ($value == '') {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Delete($dataSetColumnId, $rowNumber))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Deleted'));
            $response->loadFormUri = 'index.php?p=dataset&q=AddDataSetData';
        } else {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Edit($dataSetColumnId, $rowNumber, $value))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Edited'));
            $response->loadFormUri = 'index.php?p=dataset&q=EditDataSetData';
        }

        $response->hideMessage = true;
        $response->keepOpen = true;

    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForDataSet($dataSetId)
    {


        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkdatasetgroup ';
        $SQL .= '   ON `group`.GroupID = lkdatasetgroup.GroupID ';
        $SQL .= ' WHERE lkdatasetgroup.DataSetID = %d ';

        $SQL = sprintf($SQL, $dataSetId);

        if (!$results = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for dataset'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results)) {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }

    public function PermissionsForm()
    {

        $user = $this->getUser();
        $response = $this->getState();
        $helpManager = new Help($db, $user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DataSetPermissionsForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        // List of all Groups with a view/edit/delete checkbox

        $security = new DataSetGroupSecurity($this->db);

        if (!$results = $security->ListSecurity($dataSetId, $user->getGroupFromId($user->userid, true))) {
            trigger_error(__('Unable to get permissions for this dataset'), E_USER_ERROR);
        }

        $checkboxes = array();

        foreach ($results as $row) {
            $groupId = $row['groupid'];
            $rowClass = ($row['isuserspecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                'id' => $groupId,
                'name' => \Xibo\Helper\Sanitize::string($row['group']),
                'class' => $rowClass,
                'value_view' => $groupId . '_view',
                'value_view_checked' => (($row['view'] == 1) ? 'checked' : ''),
                'value_edit' => $groupId . '_edit',
                'value_edit_checked' => (($row['edit'] == 1) ? 'checked' : ''),
                'value_del' => $groupId . '_del',
                'value_del_checked' => (($row['del'] == 1) ? 'checked' : ''),
            );

            $checkboxes[] = $checkbox;
        }

        $formFields = array();
        $formFields[] = Form::AddPermissions('groupids[]', $checkboxes);

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DataSetPermissionsForm").submit()');

    }

    public function Permissions()
    {



        $user = $this->getUser();
        $response = $this->getState();


        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $groupIds = \Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Unlink all
        $security = new DataSetGroupSecurity($db);
        if (!$security->UnlinkAll($dataSetId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach ($groupIds as $groupPermission) {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first) {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId) {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($dataSetId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'), E_USER_ERROR);

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1]) {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first) {
            if (!$security->Link($dataSetId, $lastGroupId, $view, $edit, $del))
                trigger_error(__('Unable to set permissions'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));

    }

    public function ImportCsvForm()
    {
        global $session;

        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();

        $session->setSecurityToken($securityToken);

        // Find the max file size
        $maxFileSizeBytes = convertBytes(ini_get('upload_max_filesize'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetImportCsvForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=ImportCsv');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" />');

        Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
        Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $maxFileSizeBytes . '" />');

        Theme::Set('prepend', Theme::RenderReturn('form_file_upload_single'));

        $formFields = array();
        $formFields[] = Form::AddCheckbox('overwrite', __('Overwrite existing data?'),
            NULL,
            __('Erase all content in this DataSet and overwrite it with the new content in this import.'),
            'o');

        $formFields[] = Form::AddCheckbox('ignorefirstrow', __('Ignore first row?'),
            NULL,
            __('Ignore the first row? Useful if the CSV has headings.'),
            'i');

        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL = "";
        $SQL .= "SELECT DataSetColumnID, Heading ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $i = 0;

        foreach ($dataSetColumns as $row) {
            $i++;

            $formFields[] = Form::AddNumber('csvImport_' . \Xibo\Helper\Sanitize::int($row['DataSetColumnID']),
                \Xibo\Helper\Sanitize::string($row['Heading']), $i, NULL, 'c');
        }

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('CSV Import'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'ImportCsv') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#DataSetImportCsvForm").submit()');

    }

    public function ImportCsv()
    {


        $response = $this->getState();
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $overwrite = \Xibo\Helper\Sanitize::getCheckbox('overwrite');
        $ignorefirstrow = \Xibo\Helper\Sanitize::getCheckbox('ignorefirstrow');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // File data
        $tmpName = \Xibo\Helper\Sanitize::getString('hidFileID');

        if ($tmpName == '')
            trigger_error(__('Please ensure you have picked a file and it has finished uploading'), E_USER_ERROR);

        // File name and extension (original name)
        $fileName = \Xibo\Helper\Sanitize::getString('txtFileName');
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // Check it is a CSV file
        if ($ext != 'csv')
            trigger_error(__('Files with a CSV extension only.'), E_USER_ERROR);

        // File upload directory.. get this from the settings object
        $csvFileLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $tmpName;

        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL = "";
        $SQL .= "SELECT DataSetColumnID ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $spreadSheetMapping = array();

        foreach ($dataSetColumns as $row) {

            $dataSetColumnId = \Xibo\Helper\Sanitize::int($row['DataSetColumnID']);
            $spreadSheetColumn = \Kit::GetParam('csvImport_' . $dataSetColumnId, _POST, _INT);

            // If it has been left blank, then skip
            if ($spreadSheetColumn != 0)
                $spreadSheetMapping[($spreadSheetColumn - 1)] = $dataSetColumnId;
        }

        $dataSetObject = new DataSetData($db);

        if (!$dataSetObject->ImportCsv($dataSetId, $csvFileLocation, $spreadSheetMapping, ($overwrite == 1), ($ignorefirstrow == 1)))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('CSV File Imported'));

    }
}

?>