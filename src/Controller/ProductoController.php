<?php

namespace App\Controller;

use App\Entity\Producto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/producto', name: 'producto_')]
class ProductoController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/listar', name: 'listar', methods: ['GET'])]
    public function listar(): Response
    {
        $productos = $this->entityManager->getRepository(Producto::class)->findAll();

        return $this->render('producto/listar.html.twig', [
            'productos' => $productos,
        ]);
    }

    #[Route('/crear', name: 'crear', methods: ['POST'])]
    public function crear(Request $request): Response
    {
        $clave = $request->request->get('clave_producto');
        $nombre = $request->request->get('nombre');
        $precio = $request->request->get('precio');

        try {
            // Llamar al procedimiento almacenado
            $connection = $this->entityManager->getConnection();
            $sql = 'CALL sp_insertar_producto(:clave, :nombre, :precio)';
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery([
                'clave' => $clave,
                'nombre' => $nombre,
                'precio' => $precio,
            ]);

            // Leer el mensaje devuelto por el procedimiento
            $mensaje = $result->fetchAssociative()['mensaje'] ?? 'Error desconocido';

            // Evaluar el mensaje devuelto
            if (str_contains($mensaje, 'ERROR')) {
                $this->addFlash('danger', $mensaje);
                return $this->redirectToRoute('producto_nuevo');
            }

            $this->addFlash('success', $mensaje);
            return $this->redirectToRoute('producto_listar');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error al registrar el producto: ' . $e->getMessage());
            return $this->redirectToRoute('producto_nuevo');
        }
    }

    #[Route('/nuevo', name: 'nuevo', methods: ['GET'])]
    public function nuevo(): Response
    {
        return $this->render('producto/crear.html.twig');
    }

    // editar productos
    #[Route('/editar/{id}', name: 'editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, Producto $producto): Response
    {
        if ($request->isMethod('POST')) {
            $producto->setNombre($request->request->get('nombre'));
            $producto->setPrecio((float)$request->request->get('precio'));

            $this->entityManager->flush();

            $this->addFlash('success', 'Producto actualizado con éxito');
            return $this->redirectToRoute('producto_listar');
        }

        return $this->render('producto/editar.html.twig', [
            'producto' => $producto,
        ]);
    }

    // Eliminar producto
    #[Route('/eliminar/{id}', name: 'eliminar', methods: ['POST'])]
    public function eliminar(Request $request, Producto $producto): Response
    {
        if ($this->isCsrfTokenValid('delete' . $producto->getIdProducto(), $request->request->get('_token'))) {
            $this->entityManager->remove($producto);
            $this->entityManager->flush();

            $this->addFlash('success', 'Producto eliminado con éxito');
        }

        return $this->redirectToRoute('producto_listar');
    }

    //Exportar datos a excel
    #[Route('/exportar', name: 'exportar', methods: ['GET'])]
    public function exportar(): Response
    {
        ob_start();
        
        $productos = $this->entityManager->getRepository(Producto::class)->findAll();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Clave');
        $sheet->setCellValue('C1', 'Nombre');
        $sheet->setCellValue('D1', 'Precio');

        // Datos
        $row = 2;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto->getIdProducto());
            $sheet->setCellValue('B' . $row, $producto->getClaveProducto());
            $sheet->setCellValue('C' . $row, $producto->getNombre());
            $sheet->setCellValue('D' . $row, $producto->getPrecio());
            $row++;
        }

        // Guardar como archivo Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'productos.xlsx';

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        $writer->save('php://output');

        return $response;

        ob_end_clean();
    }
}
