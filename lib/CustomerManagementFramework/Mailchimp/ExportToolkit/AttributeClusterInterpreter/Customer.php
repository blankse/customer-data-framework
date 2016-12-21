<?php

namespace CustomerManagementFramework\Mailchimp\ExportToolkit\AttributeClusterInterpreter;

use CustomerManagementFramework\Factory;
use CustomerManagementFramework\Model\CustomerInterface;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Object\AbstractObject;

class Customer extends AbstractMailchimpInterpreter
{
    /**
     * This method is executed before the export is launched.
     * For example it can be used to clean up old export files, start a database transaction, etc.
     * If not needed, just leave the method empty.
     */
    public function setUpExport()
    {
        // noop
    }

    /**
     * This method is executed after all defined attributes of an object are exported.
     * The to-export data is stored in the array $this->data[OBJECT_ID].
     * For example it can be used to write each exported row to a destination database,
     * write the exported entries to a file, etc.
     * If not needed, just leave the method empty.
     *
     * @param AbstractObject|CustomerInterface $object
     */
    public function commitDataRow(AbstractObject $object)
    {
        // noop
    }

    /**
     * This method is executed after all objects are exported.
     * If not cleaned up in the commitDataRow-method, all exported data is stored in the array $this->data.
     * For example it can be used to write all data to a xml file or commit a database transaction, etc.
     *
     */
    public function commitData()
    {
        if (count($this->data) === 1) {
            $objectId = array_keys($this->data)[0];
            $entry    = $this->transformMergeFields($this->data[$objectId]);

            $this->commitSingle($objectId, $entry);
        } else {
            $this->commitBatch();
        }
    }

    /**
     * Commit a single entry to the API
     *
     * @param $objectId
     * @param array $entry
     */
    protected function commitSingle($objectId, array $entry)
    {
        $exportService = $this->getExportService();
        $apiClient     = $exportService->getApiClient();
        $remoteId      = $apiClient->subscriberHash($entry['email_address']);

        /** @var CustomerInterface|ElementInterface $customer */
        $customer = Factory::getInstance()->getCustomerProvider()->getById($objectId);

        $this->logger->info(sprintf(
            '[MailChimp][CUSTOMER %s] Exporting customer with remote ID %s',
            $objectId,
            $remoteId
        ));

        if ($exportService->wasExported($customer)) {
            $this->logger->info(sprintf(
                '[MailChimp][CUSTOMER %s] Customer already exists remotely with remote ID %s',
                $objectId,
                $remoteId
            ));
        } else {
            $this->logger->info(sprintf(
                '[MailChimp][CUSTOMER %s] Customer was not exported yet',
                $objectId
            ));
        }

        // always PUT as API handles both create and update on PUT and we don't need to remember a state
        $result = $apiClient->put(
            $exportService->getListResourceUrl(sprintf('members/%s', $remoteId)),
            $entry
        );

        if ($apiClient->success()) {
            $this->logger->info(sprintf(
                '[MailChimp][CUSTOMER %s] Export was successful. Remote ID is %s',
                $objectId,
                $remoteId
            ));

            // add note
            $exportService
                ->createExportNote($customer, $result['id'])
                ->save();
        } else {
            $this->logger->error(sprintf(
                '[MailChimp][CUSTOMER %s] Export failed: %s %s',
                $objectId,
                json_encode($apiClient->getLastError()),
                $apiClient->getLastResponse()['body']
            ));
        }
    }

    protected function commitBatch()
    {
        $objectIds = array_keys($this->data);

        // naive implementation exporting every customer as single request - TODO use mailchimp's batches for large exports
        foreach ($objectIds as $objectId) {
            $this->commitSingle($objectId, $this->transformMergeFields($this->data[$objectId]));
        }
    }

    /**
     * This method is executed of an object is not exported (anymore).
     * For example it can be used to remove the entries from a destination database, etc.
     *
     * @param AbstractObject $object
     */
    public function deleteFromExport(AbstractObject $object)
    {
        // noop
    }

    /**
     * Transform configured merge fields into merge_fields property
     *
     * @param array $dataRow
     * @return array
     */
    protected function transformMergeFields(array $dataRow)
    {
        $config      = (array)$this->config;
        $mergeFields = (isset($config['merge_fields'])) ? (array)$config['merge_fields'] : [];

        $result = [];
        foreach ($dataRow as $key => $value) {
            if (isset($mergeFields[$key])) {
                $result['merge_fields'][$mergeFields[$key]] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        if ($result['merge_fields']) {
            foreach ($result['merge_fields'] as $key => $value) {
                if (null === $value || false === $value) {
                    $result['merge_fields'][$key] = '';
                }
            }
        }

        return $result;
    }
}