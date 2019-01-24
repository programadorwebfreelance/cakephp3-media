<?php

namespace Media\Controller;

use Cake\Event\Event;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\ForbiddenException;
use Cake\Network\Exception\NotFoundException;

class MediasController extends AppController
{

    public function canUploadMedias($ref, $refId)
    {

        if (method_exists('\App\Controller\Admin\AppController',
            'canUploadMedias')) {
            return \App\Controller\Admin\AppController::canUploadMedias($ref,
                $refId);
        } elseif (method_exists('\App\Controller\Franquicia\AppController',
            'canUploadMedias')) {
            return \App\Controller\Franquicia\AppController::canUploadMedias($ref,
                $refId);
        } else {
            return false;
        }

    }

    public function beforeFilter(Event $event)
    {

        parent::beforeFilter($event);
        $this->viewBuilder()->setLayout('uploader');
        if (in_array('Security', $this->components()->loaded())) {

            $this->Security->setConfig('unlockedActions', ['index',
                'edit',
                'upload',
                'order',
                'thumb',
                'update',
                'delete']);
        }

    }

    public function index($ref, $refId)
    {

        if (!$this->canUploadMedias($ref, $refId)) {

            throw new ForbiddenException();

        }
        $this->loadModel($ref);
        $this->set(compact('ref', 'refId'));
        if (!in_array('Media', $this->{$ref}->Behaviors()->loaded())) {
            return $this->render('nobehavior');
        }
        $id = $this->getRequest()->getQuery('id') ? $this->getRequest()->getQuery('id') : false;
        $medias = $this->Medias->find('all')->where([
            'ref_id' => intval($refId),
            'ref' => $ref])->toArray();
        $medias = !empty($medias) ? $medias : [];
        $thumbID = false;
        if ($this->{$ref}->hasField('media_id')) {
            $entity = $this->{$ref}->get($refId);
            $thumbID = $entity->media_id;
        }
        $extensions = $this->{$ref}->medias['extensions'];
        $editor = $this->getRequest()->getQuery('editor') ? $this->getRequest()->getQuery('editor') : false;
        $this->set(compact('id', 'medias', 'thumbID', 'editor', 'extensions'));

    }

    public function edit($id = null)
    {

        $id = $this->getRequest()->getQuery('media_id');
        $data = [];
        if (intval($id)) {

            if (!$media = $this->Medias->find()
                ->where([
                    'id' => $id
                ])->first()) {

                throw new NotFoundException();

            }
            if (!$this->canUploadMedias($media->ref, $media->ref_id)) {
                throw new ForbiddenException();
            }
            $data['src'] = $media['file'];
            $data['alt'] = basename($media['file']);
            $data['class'] = '';
            $data['caption'] = $media['caption'];
            $data['editor'] = $this->getRequest()->getQuery('editor') ? $this->getRequest()->getQuery('editor') : false;
            $data['ref'] = $media->ref;
            $data['ref_id'] = $media->ref_id;
            $data['type'] = $media->file_type;

        }
        $data = \array_merge($data, $this->getRequest()->getQuery());
        $this->set(compact('data'));

    }

    public function upload($ref, $refId)
    {

        if (!$this->canUploadMedias($ref, $refId)) {

            throw new ForbiddenException();

        }
        $this->disableAutoRender();
        $data = [
            'ref' => $ref,
            'ref_id' => $refId,
            'file' => $this->getRequest()->getData()
        ];
        $media = $this->Medias->newEntity();
        $media = $this->Medias->patchEntity($media, $data, [
            'validate' => 'default'
        ]);
        if ($media->errors()) {

            echo json_encode([
                'error' => $media->errors()
            ]);

            return;

        } else {

            $media = $this->Medias->save($media,
                $this->getRequest()->getData());

        }
        $this->loadModel($ref);
        $thumbID = $this->{$ref}->hasField('media_id');
        $editor = $this->getRequest()->getQuery('editor') ? $this->getRequest()->getQuery('editor') : false;
        $id = $this->getRequest()->getQuery('id') ? $this->getRequest()->getQuery('id') : false;
        $this->set(\compact('media', 'thumbID', 'editor', 'id'));
        $this->viewBuilder()->setLayout('json');
        $this->render('media');

    }

    public function update($id)
    {

        if (!$this->getRequest()->is('ajax')) {
            throw new BadRequestException();
        }

        $this->disableAutoRender();

        if ($this->getRequest()->is([
            'put',
            'post'
        ])) {

            if (!$media = $this->Medias->find()
                ->where([
                    'id' => $id
                ])->first()) {

                throw new NotFoundException();

            }
            if (!$this->canUploadMedias($media->ref, $media->ref_id)) {

                throw new ForbiddenException();

            }
            $data = [];
            $data['name'] = $this->getRequest()->getData('name') ? $this->getRequest()->getData('name') : null;
            $data['caption'] = $this->getRequest()->getData('caption') ? $this->getRequest()->getData('caption') : null;
            $media = $this->Medias->patchEntity($media, $data, [
                'validate' => false
            ]);
            $this->Medias->save($media);

        }

    }

    public function delete($id)
    {

        $this->disableAutoRender();

        if (!$this->getRequest()->is(['ajax'])) {

            throw new BadRequestException();

        }

        if (!$media = $this->Medias->find()
            ->where([
                'id' => $id
            ])->first()) {

            throw new NotFoundException();
        }

        if (!$this->canUploadMedias($media->ref, $media->ref_id)) {
            throw new ForbiddenException();
        }

        $this->Medias->delete($media, [
            'atomic' => false
        ]);

    }

    public function thumb($id)
    {

        if (!$media = $this->Medias->find()
            ->select([
                'ref',
                'ref_id'
            ])
            ->where([
                'id' => $id
            ])
            ->first()) {
            throw new NotFoundException();
        }

        $ref = $media->ref;
        $refId = $media->ref_id;

        if (!$this->canUploadMedias($ref, $refId)) {
            throw new ForbiddenException();
        }

        $this->loadModel($ref);
        $entity = $this->{$ref}->get($refId);
        $entity->media_id = $id;
        $this->{$ref}->save($entity);

        $this->redirect([
            'action' => 'index',
            $ref,
            $refId
        ]);

    }

    public function order()
    {

        $this->viewBuilder()->setLayout('');
        $this->disableAutoRender();
        if (!$this->getRequest()->is(['ajax'])) {
            throw new BadRequestException();
        }
        if (!$this->getRequest()->getData()) {

            $id = key($this->getRequest()->getData());
            $media = $this->Medias->get(intval($id), [
                'fields' => [
                    'ref',
                    'ref_id'
                ]
            ]);

            if (!$this->canUploadMedias($media->ref, $media->ref_id)) {
                throw new ForbiddenException();
            }

            foreach ($this->getRequest()->getData() as $k => $v) {
                $media = $this->Medias->get($k);
                $media->position = $v;
                $media = $this->Medias->save($media, [
                    'validate' => false
                ]);
            }

        }

    }

}
