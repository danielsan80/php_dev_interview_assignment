<?php
namespace Dan\Yakimbi;

use Guzzle\Http\Client as GuzzleClient;
use Symfony\Component\HttpFoundation\Response;

class Application extends BaseApplication
{
    private $guzzleClient;
    
    public function setGuzzleClient(GuzzleClient $guzzleClient)
    {
        $this->guzzleClient = $guzzleClient;
    }
    
    private function getGuzzleClient()
    {
        if (!$this->guzzleClient) {
            $this->guzzleClient = new GuzzleClient();
        }
        
        return $this->guzzleClient;
    }
    
    protected function getResponse()
    {
        $request = $this->getRequest();
        $route = $request->getRequestUri();
        
        if ($route=='/') {
            return $this->homeAction();
        }
        
        if (preg_match('/^\/favorites[\/]?$/',$route, $matches)) {
            return $this->favoritesAction();
        }
        if (preg_match('/^\/api_client_test[\/]?$/',$route)) {
            return $this->apiClientTestAction();
        }
        
        if (preg_match('/^\/api\/v1\/random_images[\/]?$/',$route)) {
            return $this->apiRandomImagesAction();
        }
        
        if (preg_match('/^\/api\/v1\/favorites[\/]?$/',$route)) {
            return $this->apiFavoritesAction();
        }
        
        if (preg_match('/^\/api\/v1\/favorites\/(?P<id>\w+)[\/]?$/',$route, $matches)) {
            return $this->apiFavoriteAction($matches['id']);
        }
        
        return $this->notFoundAction();
    }
    
    public function homeAction()
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() != 'GET') {
            return new Response('Method not allowed', 405);
        }
        
        $flickr = new Service\FlickrService($this->getGuzzleClient());
        $images = $flickr->getRandomImages(20);

        return $this->render('home.html.twig', array(
            'route' => 'home',
            'images' => $images
        ));
    }
    
    public function favoritesAction()
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() != 'GET') {
            return new Response('Method not allowed', 405);
        }
        
        $imageMan = new Model\ImageManager();
        $store = new Model\Store('/data', 'images.yml', 'id');
        $imageMan->setStore($store);

        $images = $imageMan->getAll();
        
        return $this->render('favorites.html.twig', array(
            'route' => 'favorites',
            'images' => $images
        ));
    }
    
    public function apiClientTestAction()
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() != 'GET') {
            return new Response('Method not allowed', 405);
        }
        
        $client = new Service\APIClient();
        return new Response(var_dump($client->getRandomImages()));
//        return new Response(var_dump($client->setFavorite('8478239175','http://farm9.staticflickr.com/8383/8478239175_52ddd49a21_m.jpg', 'asdfasdf')));
//        return new Response(var_dump($client->setFavorite('8478239175','http://farm9.staticflickr.com/8383/8478239175_52ddd49a21_m.jpg', '')));
//        return new Response(var_dump($client->removeFavorite('8478239175')));
//        return new Response(var_dump($client->getFavorites()));
    }
    
    public function apiRandomImagesAction()
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() != 'GET') {
            return new Response('Method not allowed', 405);
        }
        $flickr = new Service\FlickrService($this->getGuzzleClient());
        $images = $flickr->getRandomImages(20);

        return $this->createApiResponse($images);
        
    }

    public function apiFavoriteAction($id=null)
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() == 'PUT') {
            $imageMan = new Model\ImageManager();
            $store = new Model\Store('/data', 'images.yml', 'id');
            $imageMan->setStore($store);

            $image = $imageMan->find($id);
            try {
                $image->bind(json_decode($request->getContent()));
                $imageMan->save($image);

                return $this->createApiResponse($image->toArray());
            } catch (\Exception $e) {
        
                return $this->createApiErrorResponse('Bad request', 400);
            }
        }
        
        if ($request->getMethod() == 'DELETE') {
            $imageMan = new Model\ImageManager();
            $store = new Model\Store('/data', 'images.yml', 'id');
            $imageMan->setStore($store);

            $image = $imageMan->find($id);
            try {
                $imageMan->remove($image);

                return $this->createApiResponse($image->toArray());
            } catch (\Exception $e) {
                
                return $this->createApiErrorResponse('Bad request', 400);
            }
        }
        
        return $this->createApiErrorResponse('Method not allowed', 405);
    }
    
    
    public function apiFavoritesAction()
    {
        $request = $this->getRequest();
        
        if ($request->getMethod() != 'GET') {
            return $this->createApiErrorResponse('Method not allowed', 405);
        }

        $imageMan = new Model\ImageManager();
        $store = new Model\Store('/data', 'images.yml', 'id');
        $imageMan->setStore($store);

        $images = $imageMan->getAll();
        
        foreach($images as $i => $image) {
            $images[$i] = $image->toArray();
        }
        
        return $this->createApiResponse($images);
    }
    
    private function createApiResponse($data, $code = 200)
    {
        return new Response(json_encode($data), $code, array('Content-Type' => 'application/json'));
    }
    
    private function createApiErrorResponse($message, $code)
    {
        $data = array('error' => $message);
        return new Response(json_encode($data), $code, array('Content-Type' => 'application/json'));
    }
}