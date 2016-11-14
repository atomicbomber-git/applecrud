<?php

namespace App\Middleware;

class AuthorizationMiddleware
{
	public function __invoke($req, $res, $next)
	{	
		/* Check if user has been logged in */
		if (! isset($_SESSION["log_in"]) ) {
			return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/login');
		}

		$res = $next($req, $res);
		return $res;
	}
}